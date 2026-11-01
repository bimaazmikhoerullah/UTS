# 🐰 RabbitMQ Topology & Async Architecture — Warung Pak Hendy

Dokumen ini menjelaskan rancangan topologi _Message Broker_ (**RabbitMQ**) serta mekanisme komunikasi asinkron untuk **Sistem Manajemen Operasional & Kasir Digital Warung Pak Hendy** (Kelompok 67).

## 🎯 1. Tujuan & Peran Message Broker

RabbitMQ digunakan sebagai middleware komunikasi asinkron antara **Inventory Backend Service** (Publisher) dan **Supplier Order Service** (Consumer). Integrasi ini bertujuan untuk:

1. **Decoupling (Pelepasan Ketergantungan):** Backend inventori tidak perlu mengetahui sistem internal yang digunakan oleh supplier/agen eksternal.
    
2. **Temporal Decoupling:** Pengiriman notifikasi _restock_ dapat dilakukan kapan saja tanpa harus menunggu layanan supplier dalam kondisi _online_.
    
3. **Buffering & Load Leveling:** Menampung lonjakan event transaksi di jam-jam sibuk warung agar dapat dikonsumsi oleh layanan penerima sesuai kapasitasnya.
    

## 🌐 2. Topologi Broker & Routing Key

```
                                  [ Publisher ]
                            Inventory Backend Service
                                        │
                                        │ (Publish Event: StockThresholdReached)
                                        ▼
                   [ Exchange: inventory.events (Topic, Durable) ]
                                        │
             ┌──────────────────────────┴──────────────────────────┐
             │ Binding: stock.threshold.reached                   │ Binding: stock.*
             ▼                                                     ▼
[ Queue: supplier.orders.queue ]                         [ Queue: analytics.stock.queue ]
 (x-dead-letter-exchange: inventory.dlx)                          │
             │                                                     ▼
             │ (Jika gagal / NACK)                      Dashboard Analytics Service
             ▼
[ Exchange: inventory.dlx (Fanout) ]
             │
             ▼
[ Queue: supplier.orders.dlq ] (Dead Letter Queue)
```

## ⚙️ 3. Spesifikasi Komponen RabbitMQ

### A. Exchanges

|   |   |   |   |   |
|---|---|---|---|---|
|**Nama Exchange**|**Tipe**|**Durable**|**Auto-Delete**|**Deskripsi**|
|`inventory.events`|`topic`|`true`|`false`|Exchange utama untuk mempublikasikan seluruh _Domain Events_ dari domain inventori.|
|`inventory.dlx`|`fanout`|`true`|`false`|Dead Letter Exchange penampung pesan gagal dari antrean inventori.|

### B. Queues & Arguments

|   |   |   |   |
|---|---|---|---|
|**Nama Queue**|**Durable**|**Arguments / Attribute**|**Fungsi / Peran**|
|`supplier.orders.queue`|`true`|`x-dead-letter-exchange: inventory.dlx`<br><br>  <br><br>`x-dead-letter-routing-key: supplier.orders.dlq`|Menampung event stok menipis untuk diproses menjadi pesanan restock ke supplier.|
|`supplier.orders.dlq`|`true`|-|Dead Letter Queue isolasi pesan rusak/gagal dari alur supplier.|
|`analytics.stock.queue`|`true`|-|Menampung event perubahan stok untuk pembaruan data dashboard secara _near real-time_.|

### C. Bindings

|   |   |   |
|---|---|---|
|**Source Exchange**|**Destination Queue**|**Routing Key Pattern**|
|`inventory.events`|`supplier.orders.queue`|`stock.threshold.reached`|
|`inventory.events`|`analytics.stock.queue`|`stock.*`|
|`inventory.dlx`|`supplier.orders.dlq`|`#` (All messages)|

## 📦 4. Struktur Payload & Header Pesan

Setiap pesan yang dipublikasikan wajib melampirkan header metadata standar AMQP 0-9-1 untuk menjamin persistensi dan keamanan data:

- **Delivery Mode:** `2` (PERSISTENT)
    
- **Content-Type:** `application/json`
    
- **Message Properties:**
    
    - `message_id`: UUIDv4 unik untuk pengecekan idempoten.
        
    - `timestamp`: Unix timestamp waktu diterbitkannya pesan.
        
    - `app_id`: `warung-backend-inventory`
        

### Contoh Payload JSON:

```
{
  "event_id": "c7a8b910-1234-5678-9abc-def012345678",
  "nama_event": "StockThresholdReached",
  "versi_skema": "1.0.0",
  "waktu_kejadian": "2026-11-01T11:35:00+07:00",
  "data": {
    "sku": "BRG-002",
    "nama_produk": "Gula Pasir Gulaku 1kg",
    "sisa_stok": 3,
    "stok_minimum": 10,
    "rekomendasi_jumlah_restock": 20
  }
}
```

## 🛡️ 5. Strategi Jaminan Pengiriman & Keandalan (Reliability)

Untuk mencegah kehilangan pesan (_message loss_) atau duplikasi transaksi, diterapkan strategi tiga tingkat:

1. **Publisher Confirms:**
    
    - Backend Core mengaktifkan fitur _Publisher Confirms_ saat mengirim pesan ke RabbitMQ.
        
    - Transaksi dianggap selesai sempurna hanya setelah RabbitMQ mengembalikan balasan `ack` konfirmasi penyimpanan ke disk.
        
2. **Manual Acknowledgement & Prefetch Count:**
    
    - Consumer (`Supplier Order Service`) diset menggunakan `basic_qos(prefetch_count=1)`.
        
    - Consumer **tidak menggunakan** `auto_ack`. Sinyal `ack` hanya dikirimkan ke broker _setelah_ operasi penyimpanan pesanan ke basis data supplier berhasil dilakukan.
        
3. **Idempotent Handler Pattern:**
    
    - Karena RabbitMQ menganut prinsip pengiriman _At-Least-Once_, duplikasi pesan dapat terjadi saat masalah jaringan.
        
    - Consumer diwajibkan melakukan pengecekan `event_id` pada tabel `processed_events` di database lokal sebelum memproses logika bisnis pesanan ulang.