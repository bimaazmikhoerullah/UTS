# 🛒 Warung Kelontong Pak Hendy — Sistem Manajemen Kasir Digital & Supply Chain Terdistribusi

> **Proyek Akhir Mata Kuliah:** Integrasi Sistem dan Komputasi Awan (25IF1504)  
> **Program Studi:** Teknik Informatika (S1) — STMIK Bandung (Kurikulum 2025)  
> **Dosen Pengampu:** Mochamad Rizki Pratama Suhernawan  
> **Kelompok 67:**
> - **Difki Junaedi** (Infrastructure Setup, Docker Compose, Backend Core & Event Publisher)
> - **Nurul Hidayah** (REST API Controller, Validasi RFC 9457 Problem Details, Feature Testing)
> - **Bima Azmi Khoerullah** (Supplier Order Service Consumer, DLQ Handler, Idempotency Logic)

---

## 📌 1. Gambaran Umum & Masalah Integrasi (Data Silo)

Warung Kelontong Pak Hendy menghadapi tantangan operasional berupa **data silo** yang memisahkan pencatatan transaksi kasir harian, pengecekan fisik barang gudang, dan pemesanan ulang (*reorder*) ke agen supplier grosir via chat manual. Akibatnya timbul risiko:
1. **Overselling:** Kasir menjual produk yang fisiknya telah habis di gudang.
2. **Lost Sales:** Keterlambatan memesan barang *fast-moving* (minyak goreng, beras, gula, mie instan).
3. **Rekonsiliasi Manual:** Selisih antara nota transaksi fisik dan saldo stok akhir hari.

Sistem ini memadukan **dua gaya integrasi utama (Hohpe & Woolf)**:
- **Jalur Sinkron (REST API):** Menghubungkan POS Kasir ke Inventory Backend dengan konfirmasi instan transaksi (`201 Created` / `409 Conflict`) dan *Pessimistic Locking*.
- **Jalur Asinkron (RabbitMQ):** Menghubungkan Inventory Backend ke Supplier Gateway secara longgar (*loose coupling*) saat stok mencapai atau berada di bawah batas minimum (`stock.threshold.reached`).

---

## 🏗️ 2. Topologi Arsitektur Sistem

```
┌──────────────────────────────┐
│  Aplikasi Kasir / POS Web    │ (React 19 + Inertia.js + Tailwind CSS)
└──────────────┬───────────────┘
               │
               │ HTTP / REST Sinkron (POST /api/v1/transaksi)
               │ Content-Type: application/json
               ▼
┌──────────────────────────────┐       ACID Locking       ┌────────────────────────┐
│   Inventory Backend Core     │ ───────────────────────► │    Basis Data MySQL    │
│  (Laravel 13 + PHP 8.5)      │ ◄── SELECT FOR UPDATE ── │ (products, outbox,     │
└──────────────┬───────────────┘                          │  sales_transactions)   │
               │                                          └────────────────────────┘
               │ Transactional Outbox (Publisher Confirms)
               │ AMQP 0-9-1
               ▼
┌──────────────────────────────┐
│       RabbitMQ Broker        │ (rabbitmq:4.0-management-alpine)
│ Exchange: inventory.events   │
│ Exchange DLX: inventory.dlx  │
└──────────────┬───────────────┘
               │
               │ Consume Asinkron (stock.threshold.reached)
               ▼
┌──────────────────────────────┐           Gagal Retry     ┌────────────────────────┐
│    Supplier Order Gateway    │ ────────────────────────► │   Dead Letter Queue    │
│  (Idempotent CLI Consumer)   │                           │ (supplier.orders.dlq)  │
└──────────────────────────────┘                           └────────────────────────┘
```

---

## 🚀 3. Panduan Menjalankan dari Nol

### Prasyarat
- PHP >= 8.2 (atau Docker & Docker Compose)
- Node.js >= 20 & npm
- Composer

### Langkah 1: Kloning & Install Dependency
```bash
git clone https://github.com/asukarew/UTS.git
cd UTS

# Salin berkas environment
cp .env.example .env

# Install dependency PHP & JavaScript
composer install
npm install
npm run build
```

### Langkah 2: Generate Key & Migrasi Database
```bash
php artisan key:generate

# Migrasi dan isi katalog produk awal
php artisan migrate:fresh --seed
```

### Langkah 3: Menjalankan Server Lokal
```bash
# Terminal 1: Web server & API (Port 8000)
php artisan serve

# Terminal 2 (Opsional): Consumer RabbitMQ
php artisan supplier:consume
```

*(Atau jika menggunakan Docker Compose / Laravel Sail)*:
```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate:fresh --seed
```

Akses aplikasi di browser: **[http://localhost:8000](http://localhost:8000)**

---

## 🧪 4. Menjalankan Otomasi Pengujian (Feature Tests)

Seluruh pengujian dibangun mengikuti prinsip TDD dan kontrak OpenAPI. Pengujian mencakup:
- Katalog & detail produk (`GET /v1/produk`, `GET /v1/produk/{sku}`)
- Transaksi sukses & pemotongan stok (`POST /v1/transaksi` $\rightarrow$ `201 Created`)
- Konflik stok kurang (`POST /v1/transaksi` $\rightarrow$ `409 Conflict`)
- Validasi input & RFC 9457 Problem Details (`422 Unprocessable`)
- Outbox event recording & format payload JSON

Jalankan test suite dengan satu perintah:
```bash
php artisan test
```
**Status: 116 passed (484 assertions) — 100% Green.**

---

## 📋 5. Validasi Kontrak OpenAPI (Spectral Linter)

Kontrak API dirancang dengan prinsip **Design-First** (`openapi.yaml` berbasis OpenAPI 3.1.0). Untuk menguji kepatuhan kontrak:
```bash
npx @stoplight/spectral-cli lint openapi.yaml
```
**Hasil: 0 errors, 0 warnings.**

---

## ⏱️ 6. Skrip Demonstrasi 4 Menit di Depan Dosen

### Menit 0–1: Penjelasan Diagram Arsitektur
Tunjukkan diagram arsitektur pada Bagian 2 di atas. Jelaskan:
- **Jalur Sinkron:** Kasir butuh kepastian saat itu juga apakah uang dan barang boleh diserahkan $\rightarrow$ REST API dengan *Pessimistic Locking*.
- **Jalur Asinkron:** Pemesanan ulang ke supplier tidak boleh menahan antrean kasir $\rightarrow$ RabbitMQ Topic Exchange dengan *Outbox Pattern*.

### Menit 1–3: Demonstrasi Langsung

#### A. Permintaan Sukses (`201 Created`)
```bash
curl -i -X POST "http://localhost:8000/api/v1/transaksi" \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      {"sku": "MYK-001", "quantity": 1}
    ]
  }'
```
*Hasil:* HTTP `201 Created`, stok fisik berkurang, transaksi tersimpan di database.

#### B. Permintaan Gagal — Stok Kurang (`409 Conflict` - RFC 9457)
```bash
curl -i -X POST "http://localhost:8000/api/v1/transaksi" \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      {"sku": "MYK-001", "quantity": 9999}
    ]
  }'
```
*Hasil:* HTTP `409 Conflict`, header `Content-Type: application/problem+json`, transaksi di-rollback.

#### C. Menjalankan Test Suite
```bash
php artisan test
```
*Hasil:* 116 test lulus di depan dosen.

#### D. Verifikasi Pesan Asinkron (RabbitMQ)
Buka browser di `http://localhost:15672` (RabbitMQ Management Dashboard). Tunjukkan queue `supplier.orders.queue` menerima pesan event `StockThresholdReached`.

### Menit 3–4: Tanya Jawab Anggota Kelompok
- **Difki Junaedi:** Menjawab arsitektur container Docker, publisher outbox, dan transaksi ACID.
- **Nurul Hidayah:** Menjawab aturan RFC 9457, validasi FormRequest, dan skenario feature test.
- **Bima Azmi Khoerullah:** Menjawab idempotency consumer, routing key RabbitMQ, dan penanganan DLQ.

---

## 📄 7. Dokumen Analisis PDF

Dokumen analisis resmi (format PDF 12 halaman lengkap bab per bab) tersedia pada repositori ini:  
📁 **[`laporan-integrasi-warung-pak-hendy.pdf`](./laporan-integrasi-warung-pak-hendy.pdf)** (dapat diunduh langsung untuk pengumpulan Google Classroom).
