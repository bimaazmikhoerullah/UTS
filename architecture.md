# 🏛️ System Architecture Specification — Warung Pak Hendy

Dokumen ini mendokumentasikan rancangan arsitektur tingkat tinggi (_high-level architecture_), pemetaan komponen, keputusan arsitektural (_Architecture Decision Records_ / ADR), serta evaluasi atribut kualitas untuk **Sistem Manajemen Operasional & Kasir Digital Warung Pak Hendy** (Kelompok 67).

## 🌐 1. Gambaran Besar Arsitektur Sistem (High-Level Overview)

Arsitektur sistem dirancang untuk menyelesaikan permasalahan **Data Silo** dan inkonsistensi pencatatan stok serta laporan operasional pada Warung Pak Hendy. Sistem memisahkan tanggung jawab ke dalam komponen-komponen mandiri yang dihubungkan oleh lapisan _middleware_ dengan dua pola komunikasi utama:

```
+-----------------------------------------------------------------------------------+
|                                  APLIKASI KASIR / POS                             |
|                           (Tablet / Mobile Frontend Service)                      |
+-----------------------------------------------------------------------------------+
                                          │
                                          │  HTTP/REST API (Sinkron)
                                          │  POST /api/v1/transaksi
                                          ▼
+-----------------------------------------------------------------------------------+
|                            INVENTORY BACKEND SERVICE                              |
|                           (Laravel Core + PostgreSQL DB)                          |
+-----------------------------------------------------------------------------------+
                       │                                    │
    Query / Data Access│                                    │ Publish Domain Event
                       ▼                                    ▼
+------------------------------+         +------------------------------------------+
|  DASHBOARD ANALYTICS SERVICE |         |             RABBITMQ BROKER              |
|   (Read-Optimized Analytics) |         |  (Topic Exchange: inventory.events)      |
+------------------------------+         +------------------------------------------+
                                                            │
                                                            │ Consume Event (Asinkron)
                                                            │ Routing: stock.threshold.reached
                                                            ▼
                                         +------------------------------------------+
                                         |          SUPPLIER ORDER SERVICE          |
                                         |    (Automated Restock Gateway Service)   |
                                         +------------------------------------------+
```

## 🧩 2. Rincian Komponen Utama Sistem

### A. Aplikasi Kasir / Point of Sale (POS)

- **Peran:** Antarmuka utama kasir untuk mencatat transaksi penjualan barang harian dan mencetak struk.
    
- **Gaya Integrasi:** Remote Procedure Invocation (RPI) via **REST API** (Sinkron).
    
- **Karakteristik Operasional:** Membutuhkan konfirmasi instan (`201 Created` / `409 Conflict`) sebelum menyelesaikan pembayaran dengan pelanggan.
    

### B. Inventory Backend Service (Core Service)

- **Peran:** Pusat logika bisnis inventori, pengelolaan data produk, eksekusi transaksi basis data transaksional (PostgreSQL), dan validasi aturan stok minimum.
    
- **Fitur Kunci:**
    
    - Implementasi _Pessimistic Locking_ (`lockForUpdate`) untuk menghindari _race condition_ saat transaksi simultan.
        
    - _Publisher_ event asinkron ke RabbitMQ saat stok menyentuh atau berada di bawah batas minimum (`stok <= stok_minimum`).
        
    - Penanganan galat terstandarisasi RFC 9457 (`application/problem+json`).
        

### C. Supplier Order Gateway (Supplier Order Service)

- **Peran:** Layanan penerima pesan (_consumer_) yang mendengarkan event stok menipis dan mengotomatiskan proses pembuatan _Purchase Order_ (PO) ke pihak supplier/agen eksternal.
    
- **Gaya Integrasi:** Asynchronous Messaging melalui **RabbitMQ**.
    
- **Fitur Kunci:**
    
    - Penerapan _Idempotent Consumer_ menggunakan UUID `event_id` unik.
        
    - Dukungan _Dead Letter Queue_ (DLQ) untuk isolasi pesan rusak.
        

### D. Dashboard Analytics & Laporan Keuangan

- **Peran:** Menyajikan visualisasi rekapitulasi penjualan, analisis barang terlaris (_best-seller_), dan laporan Laba/Rugi bagi pemilik warung.
    
- **Pola Integrasi:** Mengonsumsi data agregat dari Backend Core secara terpisah agar membaca data laporan tidak mengganggu performa transaksi di kasir.
    

## ⚖️ 3. Evaluasi Atribut Kualitas & Trade-off (Hohpe & Woolf)

|   |   |   |
|---|---|---|
|**Atribut Kualitas**|**Pendekatan REST API (POS → Backend)**|**Pendekatan Messaging (Backend → Supplier)**|
|**Data Timeliness**|**High / Instant:** Data stok langsung terpotong saat itu juga di database.|**Near Real-time / Eventual:** Pemesanan supplier diproses beberapa detik setelah event terbit.|
|**Coupling**|**Tight (Temporal):** Aplikasi POS bergantung pada ketersediaan langsung dari Backend Core.|**Loose (Spatial & Temporal):** Backend tidak perlu tahu apakah server supplier sedang _up_ atau _down_.|
|**Reliability**|Rentan jika jaringan terputus (dimitigasi dengan _Local Caching_ pada POS).|**High:** Pesan tersimpan di antrean broker (_Durable Queue_) hingga consumer aktif kembali.|
|**Complexity**|Rendah (Standard HTTP / JSON over Web).|Sedang-Tinggi (Membutuhkan pengelolaan broker, Exchange, DLQ, dan Idempotency).|

## 📜 4. Architecture Decision Records (ADR)

### ADR-01: Pemisahan Jalur Sinkron dan Asinkron

- **Status:** Accepted
    
- **Konteks:** Transaksi kasir butuh hasil langsung, sedangkan proses pemesanan ke supplier membutuhkan waktu dan bergantung pada pihak ketiga.
    
- **Keputusan:** Menggunakan **REST API** untuk POS-Backend, dan **RabbitMQ** untuk Backend-Supplier.
    
- **Konsekuesi:** Sistem memiliki dua gaya arsitektur berlawanan yang harus dipelihara, namun berhasil mengisolasi kegagalan (_bulkhead pattern_).
    

### ADR-02: Format Galat Terstandar RFC 9457

- **Status:** Accepted
    
- **Konteks:** Menghindari _Semantic Breakage_ di mana backend mengembalikan HTTP `200 OK` padahal stok barang habis.
    
- **Keputusan:** Seluruh galat dikembalikan menggunakan format `application/problem+json` dengan HTTP Status Code yang tepat (`404`, `409`, `422`).
    
- **Konsekuensi:** Klien POS harus menangani struktur JSON galat terstandar ini secara eksplisit.