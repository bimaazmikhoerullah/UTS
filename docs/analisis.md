# Dokumen Analisis Integrasi — Proyek Akhir
**Mata Kuliah: Integrasi Sistem dan Komputasi Awan (25IF1504)**
**Kelompok: Warung Kelontong Pak Hendy — Anggota: Difki Junaedi (3224005), Bima Azmi Khoerullah (3224017), Nurul Hidayah (3224002)**

---

## 1. Konteks Mitra

### 1.1 Organisasi
Mitra yang kami ambil sebagai kasus analisis adalah **Warung Kelontong Pak Hendy**, sebuah toko kelontong ritel yang menjual kebutuhan pokok dan sembako (beras, minyak goreng, gula pasir, tepung terigu, mie instan, telur, sabun, dan aneka perlengkapan rumah tangga) yang melayani kebutuhan warga perumahan/sekitar. Skala bisnisnya merupakan usaha mikro-kecil: satu gerai toko fisik, dikelola langsung oleh Pak Hendy bersama seorang pramuniaga/kasir untuk mengurusi penerimaan pasokan barang dari agen distributor, transaksi jual-beli harian dengan pelanggan, serta pencatatan keuangan warung.

### 1.2 Proses Bisnis yang Disentuh
Proses inti yang kami fokuskan adalah **siklus perputaran stok dan persediaan barang kelontong** — mulai dari penyesuaian stok saat kulakan/restok dari agen distributor, mutasi stok saat terjadi transaksi penjualan ke pelanggan di kasir, hingga pencatatan perubahan nilai barang tersebut ke dalam pembukuan kas warung. Saat ini proses tersebut memiliki sejumlah kendala:

1. **Restok / Kulakan:** Saat barang sembako tiba dari distributor (misal: 20 karton minyak goreng atau 10 karung beras), penambahan stok dicatat di buku stok fisik warung.
2. **Penjualan Eceran:** Kasir melayani pembeli secara cepat, namun pengurangan stok produk sering kali baru direkap di akhir hari atau saat stok di rak display sudah kosong.
3. **Pencatatan Keuangan Terpisah:** Perubahan stok tidak langsung terhubung dengan catatan keuangan warung (cashbook harian). Pak Hendy harus menyalin ulang faktur pembelian dan rekap penjualan secara manual setiap akhir pekan untuk mengetahui keuntungan kotor dan sisa persediaan.

### 1.3 Sistem yang Sudah Ada
- **Sistem Kasir (Point of Sale):** Aplikasi kasir sederhana berbasis web/desktop untuk menghitung total belanjaan dan mencetak struk belanja pembeli.
- **Sistem Pembukuan / Keuangan:** Spreadsheet (Excel / Google Sheets) yang dikelola oleh Pak Hendy untuk mencatat arus kas masuk, pengeluaran kulakan, dan estimasi keuntungan bulanan.
- **Pencatatan Persediaan (Stok):** Selama ini masih menggunakan catatan manual/kartu stok di rak gudang, belum berupa layanan terpusat yang dapat diakses otomatis oleh sistem kasir.

---

## 2. Persoalan Integrasi

Data persediaan barang dan keuangan warung tersebar di beberapa tempat terpisah dan **tidak pernah tersinkronisasi secara otomatis**:

| Data | Lokasi saat ini | Pihak yang Terdampak |
|---|---|---|
| Stok barang sembako | Catatan manual / buku gudang | Kasir, Pak Hendy (owner) |
| Riwayat transaksi kasir | Sistem kasir (POS) | Kasir, Pembeli |
| Arus kas & nilai inventori | Spreadsheet pembukuan | Pak Hendy (owner) |

**Dampak dari persoalan ini:**
- **Selisih Stok:** Angka barang yang tercatat sering berbeda dengan fisik barang yang ada di rak, mengakibatkan kasir menerima transaksi produk yang fisiknya sebenarnya sudah habis terjual.
- **Keterlambatan Restok:** Pak Hendy baru menyadari barang habis saat pembeli menanyakan produk, karena tidak ada sistem penyesuaian stok seketika (*real-time inventory*).
- **Ketiadaan Jejak Audit:** Jika terjadi kehilangan atau kerusakan barang kelontong (misal botol minyak bocor atau bungkus mie rusak), tidak ada riwayat pencatatan alasan penyesuaian stok yang terhubung ke laporan kerugian di pembukuan.

Dengan demikian, sistem mengalami fragmentasi data (**silo**) tanpa adanya satu sumber kebenaran tunggal (*single source of truth*) untuk stok barang.

---

## 3. Pendekatan & Gaya Integrasi (Pengembangan dari Tugas 3)

| Pasangan Sistem | Gaya Integrasi | Alasan Pemilihan & Trade-off |
|---|---|---|
| **Layanan Stok ↔ Sistem Kasir** | **Sinkron (RESTful API)** | Kasir membutuhkan konfirmasi ketersediaan stok secara seketika (*real-time*) sebelum mengonfirmasi pembayaran pembeli agar tidak terjadi penjualan barang kosong. **Trade-off:** Layanan stok harus memiliki latensi rendah dan ketersediaan tinggi; jika layanan stok mengalami kendala, transaksi kasir terhambat. |
| **Layanan Stok ↔ Layanan Pembukuan** | **Asinkron (Event-Driven via RabbitMQ)** | Layanan pembukuan keuangan warung tidak perlu memblokir transaksi pembeli di kasir. Yang dibutuhkan adalah kabar bahwa perubahan stok telah terjadi (*event*). Perhitungan laba rugi dan valuasi persediaan dapat diproses beberapa detik kemudian (*eventual consistency*). **Trade-off:** Konsistensi data bersifat *eventual*, namun kegagalan pada layanan pembukuan tidak akan pernah menggagalkan transaksi penjualan di kasir. |
| **Layanan Stok ↔ Web UI Admin (Inertia/React)** | **Sinkron (RESTful API)** | UI manajemen stok digunakan oleh Pak Hendy untuk memantau status produk, melihat peringatan stok habis, dan melakukan restok barang secara visual. |

Pola arsitektur ini menerapkan **Outbox Pattern**: penyesuaian data stok pada basis data dan pencatatan event perubahan dibungkus dalam **satu transaksi database ACID**, sehingga tidak akan terjadi kondisi data stok berubah tetapi event gagal diterbitkan (*dual-write problem*).

---

## 4. Rancangan Arsitektur

```
┌─────────────────────────┐          ┌──────────────────────────┐
│   Sistem Kasir (POS)     │──REST──▶│  Layanan Stok & Katalog  │
│ (Aplikasi Kasir Warung)  │          │  (Layanan 1 — dibangun   │
└─────────────────────────┘          │   di proyek ini, Laravel)│
                                     └───────────┬──────────────┘
                                                │ 1) Update tabel products
                                                │ 2) Catat baris tabel outbox
                                                │ 3) Commit transaksi DB (ACID)
                                                │
                                                ▼
                                     ┌──────────────────────────┐
                                     │   RabbitMQ (Message      │
                                     │   Broker) — Topic        │
                                     │   Exchange "toko.events" │
                                     └───────────┬──────────────┘
                                                │ Consume event
                                                ▼
                                     ┌──────────────────────────┐
                                     │  Layanan Pembukuan       │
                                     │  (Layanan 2 — pencatat    │
                                     │   mutasi kas & inventori)│
                                     └──────────────────────────┘

   ┌─────────────────────────┐
   │ Web UI Admin (React)    │──REST──▶  Layanan Stok & Katalog (monitoring stok Pak Hendy)
   └─────────────────────────┘
```

> **Catatan:** Pada Checkpoint 1 ini, **Layanan Pembukuan disimulasikan** sebagai consumer yang memverifikasi pesan event masuk ke antrean RabbitMQ (dapat diverifikasi melalui RabbitMQ Management UI pada `http://localhost:15672`), untuk membuktikan bahwa jalur asinkron bekerja secara handal melalui broker.

### Batas Tanggung Jawab Tiap Layanan
1. **Layanan Stok & Katalog (Layanan 1 — Laravel + MySQL):**
   - Mengelola katalog produk kelontong (SKU, nama, harga, stok).
   - Menjalankan penyesuaian stok secara sinkron (tambah/kurang) dengan validasi stok non-negatif.
   - Bertanggung jawab sebagai *single source of truth* data inventori dan mencatat event ke tabel `outbox`.
   - Mengirim event pesan ke RabbitMQ via `RabbitPublisher`.
2. **Layanan Pembukuan (Layanan 2 — Consumer):**
   - Mengonsumsi event `StokDisesuaikan` dari RabbitMQ berdasarkan routing key `stok.{alasan}`.
   - Mengonversi data mutasi barang menjadi catatan finansial (penjualan = penerimaan kas, restok = pengeluaran modal).
3. **Web UI Admin (Inertia + React):**
   - Dashboard monitoring stok bagi Pak Hendy: menampilkan total produk, barang hampir habis, dan form penyesuaian stok cepat.

### Jalur Sinkron (REST API)
- `GET /api/v1/produk` : Menampilkan seluruh daftar produk sembako/kelontong.
- `POST /api/v1/produk` : Mendaftarkan produk kelontong baru dengan SKU unik, harga, dan stok awal.
- `GET /api/v1/produk/{sku}` : Mengambil informasi detail produk berdasarkan kode SKU.
- `POST /api/v1/produk/{sku}/penyesuaian-stok` : Memutasi jumlah stok secara sinkron (menolak mutasi jika stok akhir negatif dengan status `409 Conflict`).

### Jalur Asinkron (Event via RabbitMQ)
- **Nama Event:** `StokDisesuaikan`
- **Exchange:** `toko.events` (Topic, Durable)
- **Routing Key:** `stok.{alasan}` (contoh: `stok.penjualan`, `stok.restok`, `stok.koreksi`)
- **Field Payload:** `sku`, `perubahan`, `alasan`, `stok_sesudah`, `harga`, `terjadi_pada`
- **Penerbit:** Layanan Stok & Katalog via Outbox Pattern
- **Penerima:** Layanan Pembukuan / Keuangan

---

## 5. Daftar Anggota & Pembagian Tugas

| Nama | NIM | Bagian yang Dikerjakan |
|---|---|---|
| **Difki Junaedi** | 3224005 | Desain arsitektur + Layanan Stok (REST controller, model, validasi, integrasi transaksi DB & outbox) |
| **Bima Azmi Khoerullah** | 3224017 | Implementasi event & RabbitMQ (publisher, exchange topic, konfigurasi routing key) |
| **Nurul Hidayah** | 3224002 | Feature test (API & outbox) + Dokumentasi (OpenAPI Spectral, README, dokumen analisis) |
