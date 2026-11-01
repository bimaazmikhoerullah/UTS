# 📖 Panduan Lengkap Sistem Warung Kelontong Pak Hendy (`penjelasan.md`)

> **Mata Kuliah:** Integrasi Sistem dan Komputasi Awan (25IF1504) — STMIK Bandung  
> **Kelompok 67:** Difki Junaedi, Nurul Hidayah, Bima Azmi Khoerullah  
> **Studi Kasus:** Digitalisasi Kasir (POS) dan Integrasi Rantai Pasok (*Supply Chain*) Warung Kelontong Pak Hendy

---

## 📑 Daftar Isi
1. [Latar Belakang & Masalah Data Silo](#1-latar-belakang--masalah-data-silo)
2. [Arsitektur Sistem (Sinkron vs Asinkron)](#2-arsitektur-sistem-sinkron-vs-asinkron)
3. [Fitur & Menu Antarmuka Web (Frontend)](#3-fitur--menu-antarmuka-web-frontend)
4. [Dokumentasi Lengkap REST API](#4-dokumentasi-lengkap-rest-api)
5. [Integrasi Message Broker (RabbitMQ)](#5-integrasi-message-broker-rabbitmq)
6. [Basis Data & Skema MySQL](#6-basis-data--skema-mysql)
7. [Panduan Operasional & Perintah Uji](#7-panduan-operasional--perintah-uji)

---

## 1. Latar Belakang & Masalah Data Silo

Warung Kelontong Pak Hendy sebelumnya beroperasi secara tradisional di mana pencatatan penjualan di kasir, pengecekan ketersediaan stok fisik di rak/gudang, dan pemesanan kembali (*reorder*) ke supplier grosir berjalan secara terpisah (**Data Silo**).

### Dampak Negatif Data Silo:
1. **Overselling (Penjualan Semu):** Kasir melayani transaksi pembeli, padahal stok fisik di gudang telah habis.
2. **Kehabisan Barang (Stockout):** Produk *fast-moving* (beras, minyak goreng, gula, mie instan) terlambat dipesan kembali ke supplier.
3. **Selisih Pembukuan:** Rekonsiliasi manual antara uang masuk di laci kasir dan catatan persediaan barang sering tidak seimbang.

Sistem ini memecahkan masalah tersebut dengan menyatukan **POS Kasir**, **Manajemen Persediaan**, dan **Gateway Supplier** ke dalam satu arsitektur terdistribusi yang tangguh (*fault-tolerant*).

---

## 2. Arsitektur Sistem (Sinkron vs Asinkron)

Sistem menggunakan dua pola integrasi (*Enterprise Integration Patterns* oleh Gregor Hohpe):

```
┌─────────────────────────────────────────┐
│        Frontend Web Kasir (POS)         │ (React 19 + Inertia.js + Tailwind)
└────────────────────┬────────────────────┘
                     │
                     │ HTTP REST Sinkron (POST /api/v1/transaksi)
                     │ Content-Type: application/json
                     ▼
┌─────────────────────────────────────────┐         ACID Transaction          ┌──────────────────────────┐
│         Backend Core (Laravel)          │ ────────────────────────────────► │     Basis Data MySQL     │
│   (Pessimistic Lock: SELECT FOR UPDATE) │ ◄────── Rollback / Commit ─────── │ (products, outbox, sales)│
└────────────────────┬────────────────────┘                                   └──────────────────────────┘
                     │
                     │ Transactional Outbox Pattern
                     │ AMQP 0-9-1
                     ▼
┌─────────────────────────────────────────┐
│          RabbitMQ Message Broker        │
│   - Topic Exchange: inventory.events    │
│   - Routing Key: stock.threshold.reached│
└────────────────────┬────────────────────┘
                     │
                     │ Consume Pesan Asinkron
                     ▼
┌─────────────────────────────────────────┐           Pesan Gagal / Error     ┌──────────────────────────┐
│         Supplier Order Gateway          │ ────────────────────────────────► │    Dead Letter Queue     │
│       (CLI Daemon / Worker PHP)         │                                   │  (supplier.orders.dlq)   │
└─────────────────────────────────────────┘                                   └──────────────────────────┘
```

### Mengapa Menggunakan Dua Jalur Berbeda?
- **Jalur Sinkron (REST API):** Digunakan pada proses transaksi kasir. Kasir membutuhkan kepastian detik itu juga (*immediate consistency*) apakah stok barang fisik mencukupi sebelum kasir menerima uang dari pembeli.
- **Jalur Asinkron (RabbitMQ):** Digunakan saat stok barang menyentuh ambang batas minimum (*reorder threshold*). Pemesanan ke supplier luar tidak boleh memperlambat atau menahan proses cetak struk kasir (*loose coupling* & *eventual consistency*).

---

## 3. Fitur & Menu Antarmuka Web (Frontend)

Halaman utama (`http://localhost:8090`) menyajikan aplikasi kasir interaktif berbasis **React 19, Inertia.js, dan Tailwind CSS**:

### A. Katalog Produk & Indikator Ambang Stok
- Menampilkan seluruh daftar produk warung (nama, SKU, harga satuan, dan sisa stok fisik).
- **Badge Status Stok:**
  - `Stok: X` (Warna Hijau): Stok dalam kondisi aman.
  - `Ambang Stok (X)` (Warna Kuning/Oranye): Stok berada pada atau di bawah batas minimum pemesanan ulang (`minimum_stock`).
  - `Habis` (Warna Merah): Stok bernilai 0, tombol tambah dinonaktifkan.
- **Pencarian Real-Time:** Input pencarian cepat berdasarkan SKU (misal `MYK-001`) atau nama barang (`Minyak Goreng`).
- **Tombol Sync Stok:** Memperbarui data katalog langsung dari REST API tanpa *reload* halaman penuh.

### B. Keranjang Belanja & Panel Kasir
- Menambah atau mengurangi kuantitas belanja per barang.
- Validasi sisi kasir: Tidak dapat menambah barang melebihi stok yang tersedia di database.
- Perhitungan otomatis subtotal per item dan total tagihan belanja (*Grand Total*).
- Tombol **"Proses Pembayaran (REST)"**: Memanggil `POST /api/v1/transaksi` ke backend secara atomik.

### C. Dialog Interaktif Struk & Galat RFC 9457
- **Jika Sukses (`201 Created`):** Menampilkan modal struk pembelian dengan Transaction ID (UUID), total harga, jumlah barang, dan konfirmasi pemotongan stok fisik.
- **Jika Stok Kurang (`409 Conflict`):** Menampilkan modal peringatan berstandar RFC 9457 `application/problem+json` yang memuat `title`, `detail`, dan `instance`.

### D. Fitur Bantuan & Evaluasi Cepat
- **Tombol cURL Demo:** Membuka modal berisi perintah cURL siap pakai untuk mendemonstrasikan respons `201`, `409`, dan eksekusi test suite di hadapan penguji/dosen.
- **Tombol Dokumen PDF:** Tautan langsung untuk membuka dan mengunduh berkas laporan analisis lengkap (`laporan-integrasi-warung-pak-hendy.pdf`).

---

## 4. Dokumentasi Lengkap REST API

Format dasar endpoint: `http://localhost:8090/api/v1`

### 1. Daftar Seluruh Produk
- **Endpoint:** `GET /api/v1/produk`
- **Deskripsi:** Mengambil katalog lengkap beserta sisa stok fisik dan batas minimum stok.
- **Respons Sukses:** `200 OK`
```json
[
  {
    "id": 1,
    "sku": "MYK-001",
    "name": "Minyak Goreng Bimoli 1L",
    "harga": 20000,
    "stock": 25,
    "minimum_stock": 5
  }
]
```

---

### 2. Detail Produk Berdasarkan SKU
- **Endpoint:** `GET /api/v1/produk/{sku}`
- **Parameter:** `sku` (contoh: `MYK-001`)
- **Respons Sukses:** `200 OK`

---

### 3. Proses Transaksi Kasir (POS)
- **Endpoint:** `POST /api/v1/transaksi`
- **Headers:** `Content-Type: application/json`
- **Request Body:**
```json
{
  "items": [
    {
      "sku": "MYK-001",
      "quantity": 2
    }
  ]
}
```

- **Respons Sukses (`201 Created`):**
```json
{
  "transaction": {
    "id": 1,
    "transaction_id": "a0f1b1ab-357c-4cd8-99d4-75a99dd68738",
    "total_price": 40000,
    "items": [
      {
        "sku": "MYK-001",
        "name": "Minyak Goreng Bimoli 1L",
        "quantity": 2,
        "price": 20000,
        "subtotal": 40000
      }
    ]
  },
  "total_price": 40000
}
```

- **Respons Gagal — Stok Tidak Cukup (`409 Conflict` - RFC 9457):**
  - **Header Respons:** `Content-Type: application/problem+json`
```json
{
  "type": "about:blank",
  "title": "Conflict",
  "status": 409,
  "detail": "STOK_TIDAK_CUKUP: MYK-001",
  "instance": "http://localhost:8090/api/v1/transaksi"
}
```

- **Respons Gagal — Input Tidak Valid (`422 Unprocessable Content`):**
```json
{
  "type": "about:blank",
  "title": "Unprocessable Content",
  "status": 422,
  "detail": "Data yang diberikan tidak memenuhi aturan validasi.",
  "invalid_params": [
    {
      "name": "items.0.quantity",
      "reason": "The items.0.quantity field must be at least 1."
    }
  ]
}
```

---

### 4. Buat Pemesanan Ulang ke Supplier (Manual Reorder)
- **Endpoint:** `POST /api/v1/persediaan/reorder`
- **Request Body:**
```json
{
  "sku": "MYK-001",
  "quantity": 10,
  "supplier": "PT Sumber Jaya Grosir",
  "notes": "Pengiriman pagi"
}
```
- **Respons Sukses:** `201 Created` (Menghasilkan record OrderStatus dengan status `pending`).

---

### 5. Riwayat Pemesanan Ulang
- **Endpoint:** `GET /api/v1/persediaan/reorder`
- **Deskripsi:** Menampilkan daftar order reorder yang sedang diproses atau sudah selesai.

---

## 5. Integrasi Message Broker (RabbitMQ)

Sistem menggunakan **RabbitMQ 4.0** untuk menangani komunikasi asinkron antara Warung Pak Hendy dan pihak supplier grosir.

### A. Topologi RabbitMQ
1. **Topic Exchange:** `inventory.events`
   - Digunakan untuk merouting event stok ke antrean yang relevan.
   - Routing Key yang didukung:
     - `stok.penjualan`: Dipublikasikan saat transaksi kasir berhasil.
     - `stok.penyesuaian`: Dipublikasikan saat ada koreksi stok gudang manual.
     - `stock.threshold.reached`: Dipicu saat stok mencapai batas kritis.
2. **Main Queue:** `supplier.orders.queue`
   - Terikat ke exchange `inventory.events` dengan binding pattern `stok.*`.
3. **Dead Letter Exchange (DLX):** `inventory.dlx`
   - Menerima pesan yang ditolak (*rejected/nack*) atau pesan yang rusak untuk mencegah hilangnya data (*poison message handling*).
4. **Dead Letter Queue (DLQ):** `supplier.orders.dlq`

### B. Transactional Outbox Pattern
Untuk mencegah kegagalan *Dual-Write* (database commit tetapi broker jaringan mati), sistem menerapkan **Outbox Pattern**:
1. Saat kasir melakukan checkout, data transaksi dan pencatatan event ke tabel `outbox` dilakukan dalam satu transaksi database ACID (`DB::transaction`).
2. Perintah `php artisan outbox:process` akan membaca data dari tabel `outbox` yang belum terkirim dan mempublikasikannya ke RabbitMQ.
3. Setelah RabbitMQ mengirimkan *Publisher Confirm*, record di tabel `outbox` diberi stempel `dikirim_pada`.

### C. Consumer Idempoten (`SupplierOrderConsumer`)
Consumer dijalankan melalui perintah:
```bash
./vendor/bin/sail artisan supplier:consume
```
Consumer mengecek keunikan payload untuk memastikan bahwa jika sebuah pesan terkirim ulang (*at-least-once delivery*), sistem tidak membuat order ganda ke supplier.

---

## 6. Basis Data & Skema MySQL

Sistem menggunakan basis data **MySQL 8.4** (`warung_db`) dengan tabel-tabel utama:

1. **`products`:**
   - `id`: Primary Key (BigInt)
   - `sku`: Kode unik barang (String, misal `MYK-001`)
   - `name`: Nama barang
   - `harga`: Harga jual satuan (Integer)
   - `stock`: Saldo stok fisik aktual (Integer)
   - `minimum_stock`: Ambang batas reorder (Integer, default 5)
2. **`sales_transactions`:**
   - `id`: Primary Key
   - `transaction_id`: UUID transaksi unik kasir
   - `total_price`: Total nilai belanja
   - `items`: JSON rincian barang, harga, dan kuantitas
3. **`outbox`:**
   - `id`: Primary Key
   - `event_id`: UUID event
   - `event_type`: Nama kelas event (`StokDisesuaikan`)
   - `payload`: Isi JSON pesan
   - `dikirim_pada`: Timestamp saat pesan berhasil masuk broker
4. **`order_statuses`:**
   - `id`: Primary Key
   - `order_id`: UUID pesanan reorder
   - `sku`: Barang yang dipesan
   - `quantity`: Jumlah pesanan restock
   - `status`: `pending`, `sent`, `confirmed`, `received`

---

## 7. Panduan Operasional & Perintah Uji

### A. Menjalankan Seluruh Layanan (Docker Sail)
```bash
cd /home/xavyera/Documents/Warung

# Jalankan container (App, MySQL 8.4, RabbitMQ 4.0)
./vendor/bin/sail up -d

# Cek status container yang berjalan
./vendor/bin/sail ps
```

### B. Menjalankan Otomasi Pengujian (Feature Tests)
```bash
./vendor/bin/sail artisan test
```
*Hasil:* **116 passed (484 assertions) — 100% Green.**

### C. Menjalankan Linter Kontrak OpenAPI (Spectral)
```bash
npx @stoplight/spectral-cli lint openapi.yaml
```
*Hasil:* **0 errors, 0 warnings.**

### D. Akses Dashboard & Layanan di Browser
- **Aplikasi Kasir POS & Beranda:** [http://localhost:8090](http://localhost:8090)
- **RabbitMQ Management Dashboard:** [http://localhost:15672](http://localhost:15672) (User: `guest` / Pass: `guest`)
- **Unduh Dokumen PDF Laporan Analisis:** [http://localhost:8090/laporan-integrasi-warung-pak-hendy.pdf](http://localhost:8090/laporan-integrasi-warung-pak-hendy.pdf)
