<div align="center">

# 🛒 Warung Kelontong Pak Hendy
### Sistem Manajemen Kasir Digital & Rantai Pasok Terdistribusi (*Supply Chain*)

[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![RabbitMQ](https://img.shields.io/badge/RabbitMQ-4.0-FF6600?style=for-the-badge&logo=rabbitmq&logoColor=white)](https://rabbitmq.com)
[![React](https://img.shields.io/badge/React-19-61DAFB?style=for-the-badge&logo=react&logoColor=black)](https://react.dev)
[![Tests](https://img.shields.io/badge/Tests-116%20Passed-2EA44F?style=for-the-badge&logo=phpunit&logoColor=white)](.)
[![OpenAPI](https://img.shields.io/badge/OpenAPI-3.1.0-6BA539?style=for-the-badge&logo=openapiinitiative&logoColor=white)](./openapi.yaml)

**Mata Kuliah:** Integrasi Sistem dan Komputasi Awan (25IF1504)  
**Program Studi:** S1 Teknik Informatika — STMIK Bandung  
**Kelompok 67:**
* **Difki Junaedi** *(Infra, Docker Compose, Outbox Publisher)*
* **Nurul Hidayah** *(REST API, RFC 9457 Error Handling, Feature Testing)*
* **Bima Azmi Khoerullah** *(RabbitMQ Consumer, DLQ Handler, Idempotency)*

---

### 📚 PUSAT NAVIGASI DOKUMENTASI PROYEK
*Klik tombol di bawah ini untuk langsung menavigasi dokumen spesifik secara otomatis di GitHub:*

| 📋 Panduan Utama | 📮 Pengujian API | 🏗️ Arsitektur & Sistem | 🗄️ Database & Kontrak |
| :---: | :---: | :---: | :---: |
| [<img src="https://img.shields.io/badge/📖_Penjelasan_Lengkap-0366d6?style=flat-square">](./penjelasan.md) | [<img src="https://img.shields.io/badge/📮_Postman_Guide-f36f24?style=flat-square">](./postman.md) | [<img src="https://img.shields.io/badge/🏗️_Architecture-6f42c1?style=flat-square">](./architecture.md) | [<img src="https://img.shields.io/badge/🗄️_Schema_Database-28a745?style=flat-square">](./schema.md) |
| [<img src="https://img.shields.io/badge/📄_Laporan_PDF_12_Hal-d73a49?style=flat-square">](./laporan-integrasi-warung-pak-hendy.pdf) | [<img src="https://img.shields.io/badge/🔌_API_Endpoints-005cc5?style=flat-square">](./api.md) | [<img src="https://img.shields.io/badge/🔄_System_Flow-007ec6?style=flat-square">](./flow.md) | [<img src="https://img.shields.io/badge/📋_API_Contracts-e36209?style=flat-square">](./contracts.md) |
| [<img src="https://img.shields.io/badge/🗺️_Project_Roadmap-586069?style=flat-square">](./roadmap.md) | [<img src="https://img.shields.io/badge/📦_Postman_JSON-f1e05a?style=flat-square">](./docs/postman/xavyera-uts.postman_collection.json) | [<img src="https://img.shields.io/badge/🐰_Message_Broker-ff6600?style=flat-square">](./broker.md) | [<img src="https://img.shields.io/badge/🎯_Test_Scenarios-6f42c1?style=flat-square">](./scenarios.md) |

</div>

---

## 📌 1. Gambaran Umum & Studi Kasus

Warung Kelontong Pak Hendy menghadapi masalah klasik operasional ritel berupa **Data Silo** (pemisahan pencatatan kasir, gudang fisik, dan pemesanan supplier via chat). Hal ini memicu risiko:
1. **Overselling:** Kasir menjual barang yang fisiknya telah kosong di gudang.
2. **Stockout:** Keterlambatan memesan ulang produk *fast-moving* (minyak goreng, beras, gula, mie instan).
3. **Rekonsiliasi Manual:** Selisih antara pencatatan keuangan dan saldo fisik barang.

Sistem ini memadukan dua gaya integrasi (*Enterprise Integration Patterns*):
* **Jalur Sinkron (REST API):** Konfirmasi instan transaksi POS kasir dengan penguncian basis data ACID (*Pessimistic Locking*).
* **Jalur Asinkron (RabbitMQ):** Pengiriman notifikasi pemesanan ulang secara longgar (*loose coupling*) ke supplier saat stok mencapai batas minimum.

---

## 🏗️ 2. Topologi Arsitektur Sistem

```mermaid
graph TD
    A[Web Kasir / POS Frontend] -->|HTTP REST Sinkron POST /api/v1/transaksi| B[Laravel Backend Core]
    B -->|Pessimistic Locking SELECT FOR UPDATE| C[(MySQL 8.4 Database)]
    B -->|Transactional Outbox Publisher| D[RabbitMQ Topic Exchange: inventory.events]
    D -->|AMQP Routing Key: stok.*| E[Supplier Order Gateway Consumer]
    E -->|Buat Order Restock| F[(Order Status Table)]
    E -.->|Gagal / Poison Message| G[Dead Letter Queue: inventory.dlx]
```

---

## 💻 3. Fitur Utama & Menu Web Aplikasi

Akses aplikasi di browser: **[http://localhost:8090](http://localhost:8090)**

1. **Dashboard & Katalog Produk:**
   * Menampilkan daftar barang, harga, dan sisa stok fisik secara *real-time*.
   * **Badge Status Stok:** Warna hijau (aman), kuning (mendekati ambang batas minimum), dan merah (habis).
2. **Pencarian Cepat & Sync Stok:** Pencarian instan berdasarkan SKU atau nama barang, dilengkapi tombol sinkronisasi API.
3. **Keranjang Kasir & POS Checkout:**
   * Menambah/mengurangi kuantitas belanja dengan validasi stok instan.
   * Tombol pembayaran untuk memanggil endpoint REST API `POST /api/v1/transaksi`.
4. **Modal Struk & Error RFC 9457:** Menampilkan struk transaksi sukses (`201 Created`) atau modal galat berstandar RFC 9457 (`409 Conflict`).
5. **cURL Demo Modal & Unduh PDF:** Kotak dialog berisi perintah cURL siap pakai untuk demo dosen serta tombol unduh naskah laporan 12 halaman (`laporan-integrasi-warung-pak-hendy.pdf`).

---

## 🔌 4. Ringkasan REST API & RFC 9457

Seluruh endpoint berada di bawah base URL `http://localhost:8090/api/v1`:

| Method | Endpoint | Deskripsi | Respons Sukses |
| :---: | :--- | :--- | :---: |
| **GET** | `/produk` | Mengambil seluruh katalog produk & stok | `200 OK` |
| **GET** | `/produk/{sku}` | Mengambil detail produk berdasarkan SKU | `200 OK` |
| **POST** | `/transaksi` | Memproses transaksi penjualan kasir (POS) | `201 Created` |
| **POST** | `/persediaan/reorder` | Membuat pemesanan ulang manual ke supplier | `201 Created` |
| **GET** | `/persediaan/reorder` | Melihat riwayat status pemesanan supplier | `200 OK` |

> **Format Galat (Error Handling):** Mengikuti standar **RFC 9457 Problem Details** dengan `Content-Type: application/problem+json` memuat properti `type`, `title`, `status`, `detail`, dan `instance`.

---

## 🚀 5. Panduan Menjalankan dari Nol

### Prasyarat
* Docker & Docker Compose (Laravel Sail)
* PHP >= 8.5 & Composer
* Node.js >= 20 & npm

### Langkah Instalasi
```bash
# 1. Kloning repositori & masuk direktori proyek
git clone https://github.com/asukarew/UTS.git
cd UTS

# 2. Salin environment & install dependency
cp .env.example .env
composer install
npm install
npm run build

# 3. Jalankan container Docker (App, MySQL 8.4, RabbitMQ 4.0)
./vendor/bin/sail up -d

# 4. Jalankan migrasi dan seeder database
./vendor/bin/sail artisan migrate:fresh --seed
```

---

## 🧪 6. Pengujian & Validasi Kontrak

### A. Otomasi Pengujian (Feature Tests)
```bash
./vendor/bin/sail artisan test
```
*Hasil:* **116 passed (484 assertions) — 100% Green.**

### B. Validasi Kontrak OpenAPI (Spectral Linter)
```bash
npx @stoplight/spectral-cli lint openapi.yaml
```
*Hasil:* **0 errors, 0 warnings.**

---

## ⏱️ 7. Skenario Demonstrasi 4 Menit di Depan Dosen

1. **Menit 0–1:** Jelaskan masalah *Data Silo* warung tradisional dan arsitektur *dual-channel* (REST sinkron untuk POS, RabbitMQ asinkron untuk supplier).
2. **Menit 1–2.5:** Demonstrasikan pemanggilan cURL transaksi sukses (`201`) dan transaksi gagal stok kurang (`409 Conflict` berformat RFC 9457).
3. **Menit 2.5–3.5:** Tunjukkan hasil *Feature Tests* (`php artisan test` 116 lulus) dan buka RabbitMQ Dashboard di `http://localhost:15672` (`guest`/`guest`).
4. **Menit 3.5–4:** Sesi tanya jawab bersama anggota kelompok (Difki, Nurul, Bima).
