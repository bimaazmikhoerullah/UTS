#  RabbitMQ Broker Infrastructure Specification — Warung Pak Hendy

Dokumen ini mendokumentasikan spesifikasi infrastruktur _Message Broker_ (**RabbitMQ 4.x**), skrip otomasi pembuatan topologi, konfigurasi keandalan pengiriman pesan, serta manajemen antrean untuk **Sistem Manajemen Operasional & Kasir Digital Warung Pak Hendy** (Kelompok 67).

## 🎛️ 1. Parameter Infrastruktur Broker

Layanan RabbitMQ dijalankan dalam lingkungan terisolasi menggunakan Container (Docker) untuk mendukung pengembangan lokal dan penyiapan sistem yang konsisten.

- **Image Docker:** `rabbitmq:4.0-management-alpine`
    
- **Port AMQP:** `5672` (Komunikasi antar-layanan / microservices)
    
- **Port HTTP Management UI:** `15672` (Monitoring visual & administrasi antrean)
    
- **VHost:** `/` (Default Virtual Host)
    
- **Kredensial Default:** `admin` / `admin123` (Environment variables)
    

## 🏗️ 2. Skrip Otomasi Topologi (Init Definitions / Shell Command)

Topologi exchange, antrean, dan _binding_ dapat didaftarkan secara otomatis saat container RabbitMQ pertama kali dihidupkan menggunakan skrip penyiapan berikut:

```
#!/bin/bash
# Script Inisialisasi Topologi RabbitMQ - Warung Pak Hendy

echo "=== Mendaftarkan Exchange ==="
# Exchange Utama (Topic)
rabbitmqadmin declare exchange name=inventory.events type=topic durable=true

# Dead Letter Exchange (Fanout)
rabbitmqadmin declare exchange name=inventory.dlx type=fanout durable=true

echo "=== Mendaftarkan Queue ==="
# Queue Utama Restock Supplier (Dengan argumen x-dead-letter-exchange)
rabbitmqadmin declare queue name=supplier.orders.queue durable=true \
  arguments='{"x-dead-letter-exchange":"inventory.dlx"}'

# Dead Letter Queue (DLQ)
rabbitmqadmin declare queue name=supplier.orders.dlq durable=true

# Queue Analytics Stok (Opsional / Extension)
rabbitmqadmin declare queue name=analytics.stock.queue durable=true

echo "=== Mendaftarkan Binding ==="
# Binding Event Stok Menipis ke Queue Supplier
rabbitmqadmin declare binding source=inventory.events destination=supplier.orders.queue routing_key=stock.threshold.reached

# Binding Event Stok ke Analytics (Wildcard)
rabbitmqadmin declare binding source=inventory.events destination=analytics.stock.queue routing_key=stock.*

# Binding Dead Letter Exchange ke Dead Letter Queue
rabbitmqadmin declare binding source=inventory.dlx destination=supplier.orders.dlq

echo "=== Inisialisasi Topologi RabbitMQ Selesai ==="
```

## 🛡️ 3. Konfigurasi Keandalan Pengiriman (_Reliability Settings_)

Untuk memastikan tidak ada pesan event restock yang hilang selama proses integrasi, diterapkan aturan keandalan sebagai berikut:

### A. Publisher Confirms (Sisi Publisher / Backend Laravel)

- Publisher mengaktifkan fitur **Publisher Confirms**. Saat backend mengirimkan pesan `StockThresholdReached`, broker akan mengirimkan konfirmasi (_ack_) kembali ke Laravel bahwa pesan telah aman disimpan ke media penyimpanan permanen (disk) broker.
    
- Jika broker gagal menyimpan pesan, publisher akan melakukan uji coba ulang (_retry mechanism_).
    

### B. Message & Queue Durability

- **Persistent Messages:** Atribut `delivery_mode` pada pesan diatur ke nilai `2` (Persistent).
    
- **Durable Exchanges & Queues:** Semua _Exchange_ dan _Queue_ didaftarkan dengan flag `durable=true` agar tidak hilang jika terjadi henti sistem (_crash_) atau restart server.
    

### C. Consumer Acknowledgement & Prefetch Count

- **Manual ACK:** _Supplier Order Service_ diwajibkan mengirimkan konfirmasi `ack` secara manual setelah logika bisnis pencatatan restock selesai dieksekusi.
    
- **Basic QoS (Prefetch Count = 1):** Consumer dikonfigurasi dengan `basic.qos(prefetch_count=1)`. Broker hanya akan memberikan 1 pesan pada satu waktu ke consumer dan menunggu `ack` sebelum mengirimkan pesan berikutnya.
    

## 🚨 4. Manajemen Dead Letter Queue (DLQ Workflow)

Pesan yang gagal diproses oleh _Supplier Order Service_ tidak boleh menghentikan antrean utama atau menyebabkan perulangan tak terbatas (_infinite loop_).

```
                      [ supplier.orders.queue ]
                                 │
                                 │ Consumer Mengolah Pesan
                                 ▼
                     [ Supplier Order Service ]
                                 │
                     Gagal / Format JSON Rusak?
                                 │
                     ┌───────────┴───────────┐
                     │ YA                    │ TIDAK
                     ▼                       ▼
            Send NACK(requeue=false)    Send ACK
                     │                 (Proses Selesai)
                     ▼
           [ Exchange: inventory.dlx ]
                     │
                     ▼
        [ Queue: supplier.orders.dlq ]
                     │
                     ▼
         (Notifikasi Alerting Admin &
          Inspeksi Manual UI RabbitMQ)
```

1. **Penyebab Dialihkan ke DLQ:**
    
    - Pesan ditolak secara eksplisit oleh consumer dengan `nack(requeue=false)` atau `reject(requeue=false)`.
        
    - Masa berlaku pesan kadaluarsa (_Time-To-Live / TTL expired_).
        
    - Kapasitas antrean utama melampaui batas (_Queue max length exceeded_).
        
2. **Monitoring & Penanganan:**
    
    - Admin warung dapat melihat daftar pesan bermasalah melalui RabbitMQ Management Dashboard pada antrean `supplier.orders.dlq`.
        
    - Setelah sumber masalah diperbaiki (misal: skema database consumer diperbarui), pesan di DLQ dapat dikirim ulang (_requeued_) ke antrean utama menggunakan fitur _Move Messages_ pada Management UI.