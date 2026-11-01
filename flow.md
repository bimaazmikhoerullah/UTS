# 🔄 System Flow & Interaction Architecture — Warung Pak Hendy

Dokumen ini mendokumentasikan alur interaksi antarsistem dan diagram urutan (_sequence diagrams_) untuk **Sistem Manajemen Operasional & Kasir Digital Warung Pak Hendy** (Kelompok 67).

Dokumen ini memisahkan secara jelas alur komunikasi **sinkron (REST API)** untuk transaksi POS kasir dan alur **asinkron (RabbitMQ Message Broker)** untuk pemesanan otomatis ke supplier (_restock_).

## 1. Alur Transaksi Kasir Sinkron (POS to Backend Core)

Alur ini memproses transaksi penjualan dari perangkat kasir (POS) ke backend inventori secara langsung (_real-time & blocking_).

### 📐 Sequence Diagram: REST API Penjualan & Pemotongan Stok

```
[ POS App / Kasir ]               [ Inventory Backend ]                [ PostgreSQL DB ]
        │                                  │                                   │
        │── 1. POST /api/v1/transaksi ────►│                                   │
        │      (Header & Payload JSON)     │── 2. Validasi FormRequest ───────►│
        │                                  │      (Cek format SKU & Nominal)   │
        │                                  │                                   │
        │                                  │── 3. BEGIN TRANSACTION ──────────►│
        │                                  │── 4. SELECT ... FOR UPDATE ──────►│ (Lock baris produk)
        │                                  │◄── 5. Data Stok Fisik ────────────│
        │                                  │                                   │
        │                                  │   [ Logic Check ]                 │
        │                                  │   Stok Cukup? ─────────────────┐  │
        │                                  │   │ YES                        │  │
        │                                  │   ▼                            │  │
        │                                  │── 6. UPDATE stok (stok - qty) ──►│  │
        │                                  │── 7. INSERT transaksi & item ───►│  │
        │                                  │── 8. COMMIT TRANSACTION ────────►│  │
        │                                  │                                │  │
        │◄── 9. HTTP 201 Created ──────────│                                │  │
        │      (Header Location + Body)    │                                │  │
        │                                  │                                ▼  │
        │                                  │ [ Logic Check ]                │ NO
        │                                  │ Stok <= Stok Minimum?          │  │
        │                                  │   │ YES                        │  │
        │                                  │   ▼                            │  │
        │                                  │ (Lanjut ke Alur Asinkron)      │  │
        │                                  │                                │  │
        │◄─────────────────────────────────┴── 10. ROLLBACK & HTTP 409 ─────┴──│
        │                                      (Problem Details: Stok Kurang)
```

### 📝 Penjelasan Langkah Alur Sinkron:

1. **Inisiasi Transaksi:** Kasir menekan tombol bayar pada aplikasi POS. Perangkat mengirimkan request `POST /v1/transaksi` ke Backend Core.
    
2. **Validasi Kontrak:** Middleware Backend memeriksa kecocokan format JSON payload dengan aturan skema (mencegah _Structural Breakage_).
    
3. **Pessimistic Locking:** Backend membuka transaksi basis data dan mengunci baris produk (`SELECT ... FOR UPDATE`) untuk mencegah _race condition_ jika dua kasir bertransaksi serentak.
    
4. **Pemeriksaan Stok:**
    
    - **Jika Stok Cukup:** Jumlah stok dikurangi, record transaksi disimpan ke database PostgreSQL, transaksi di-_commit_, dan backend merespons dengan HTTP Status `201 Created`.
        
    - **Jika Stok Kurang:** Transaksi di-_rollback_, dan backend merespons dengan HTTP Status `409 Conflict` berformat RFC 9457 _Problem Details_.
        

## 2. Alur Pemesanan Supplier Asinkron (Backend to Supplier Gateway)

Alur ini terpicu secara otomatis di latar belakang (_background job_) tanpa membebani aplikasi kasir saat transaksi penjualan selesai disetujui.

### 📐 Sequence Diagram: Event Publishing & Message Consumption

```
[ Inventory Backend ]         [ RabbitMQ Broker ]        [ Supplier Order Service ]      [ Supplier External ]
         │                             │                              │                           │
         │ (Tercetus dari Alur 1)      │                              │                           │
         │ Stok <= Stok Minimum        │                              │                           │
         │                             │                              │                           │
         │── 1. Publish Event ────────►│                              │                           │
         │   Exchange: inventory.events│                              │                           │
         │   Routing Key:              │                              │                           │
         │   stock.threshold.reached   │                              │                           │
         │                             │── 2. Route Message ─────────►│                           │
         │                             │      Queue:                  │                           │
         │                             │      supplier.orders.queue   │                           │
         │                             │                              │                           │
         │                             │                              │── 3. Consume Message ────►│
         │                             │                              │   (Parse Payload JSON)    │
         │                             │                              │                           │
         │                             │                              │── 4. Cek Unique Event ID ─┐
         │                             │                              │      (Idempotency Check)  │
         │                             │                              │◄──────────────────────────┘
         │                             │                              │
         │                             │                              │── 5. Kirim PO / Restock ─►│
         │                             │                              │      (Auto-Order/WA)      │
         │                             │                              │◄── 6. Respon Order ID ────│
         │                             │                              │                           │
         │                             │◄── 7. ACK Message ───────────│                           │
         │                             │      (Hapus dari Queue)      │                           │
```

### 📝 Penjelasan Langkah Alur Asinkron:

1. **Triggering Event:** Setelah transaksi kasir berhasil (_commit_), backend memeriksa sisa stok. Jika sisa stok $\le$ stok minimum, backend membuat event `StockThresholdReached`.
    
2. **Publishing:** Backend mempublikasikan pesan event ke RabbitMQ Topic Exchange `inventory.events` dengan _routing key_ `stock.threshold.reached`.
    
3. **Buffering Antrean:** RabbitMQ menyalurkan pesan ke antrean `supplier.orders.queue`. Jika _Supplier Order Service_ sedang mati atau _offline_, pesan aman mengantre di broker.
    
4. **Message Consumption & Idempotence Check:**
    
    - Saat _Supplier Order Service_ aktif, pesan diambil (_consumed_).
        
    - Service memeriksa `event_id` (UUID) pada tabel _processed_events_ lokal. Jika `event_id` sudah pernah diproses, pesan langsung di-`ACK` dan diabaikan (mencegah _order_ ganda).
        
5. **Proses Restock:** Jika `event_id` baru, layanan memproses pesanan restock ke pihak supplier/agen eksternal.
    
6. **Acknowledgement:** Setelah proses restock tersimpan, service mengirimkan konfirmasi `ACK` ke RabbitMQ untuk menghapus pesan dari antrean.
    

## 3. Alur Penanganan Kesalahan Pesan Asinkron (Dead Letter Queue Workflow)

Jika terjadi kegagalan saat pengolahan event di sisi _Supplier Order Service_ (misalnya format pesan rusak atau database supplier crash):

```
[ RabbitMQ Queue ]           [ Supplier Order Service ]         [ Dead Letter Exchange ]        [ Dead Letter Queue ]
(supplier.orders.queue)                  │                         (inventory.dlx)            (supplier.orders.dlq)
        │                                │                                │                             │
        │── 1. Deliver Message ─────────►│                                │                             │
        │                                │── 2. Gagal Olah / Error ──────┐│                             │
        │                                │      (Max Retry Reached)      ││                             │
        │                                │◄──────────────────────────────┘│                             │
        │                                │                                │                             │
        │                                │── 3. NACK (requeue=false) ────►│                             │
        │                                │                                │── 4. Route to DLQ ─────────►│
        │                                │                                │                             │
        │                                │                                │                             │── 5. Alert Admin &
        │                                │                                │                                │   Manual Inspection
```

1. Message mengalami error berulang saat dikonsumsi oleh _Supplier Order Service_.
    
2. Service mengirimkan instruksi `NACK(requeue=false)` ke RabbitMQ.
    
3. RabbitMQ memindahkan pesan dari antrean utama ke _Dead Letter Exchange_ (`inventory.dlx`).
    
4. Pesan dialihkan secara otomatis ke _Dead Letter Queue_ (`supplier.orders.dlq`).
    
5. Administrator warung menerima pemberitahuan untuk memeriksa pesan yang terisolasi di DLQ tanpa mengganggu lalu lintas antrean utama.