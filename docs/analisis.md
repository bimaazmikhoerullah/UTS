# Dokumen Analisis Integrasi — Proyek Akhir
**Mata Kuliah: Integrasi Sistem dan Komputasi Awan (25IF1504)**
**Kelompok: [NAMA KELOMPOK] — Anggota: [Anggota 1], [Anggota 2], [Anggota 3]**

---

## 1. Konteks Mitra

### 1.1 Organisasi
Mitra yang kami ambil sebagai kasus analisis adalah **Toko Berkah Jaya**, sebuah pengecer produk kerudung (kerudung paris, jilbab, aksesori) di Bandung dengan volume penjualan ritel harian sekitar ratusan transaksi. Skala bisnisnya masih kecil-menengah: satu outlet fisik, satu pemilik-operator yang mengurusi jual-beli sekaligus pencatatan, dan satu sistem pembukuan sederhana.

### 1.2 Proses Bisnis yang Disentuh
Proses inti yang kami fokuskan: **siklus stok** — dari penyesuaian stok (restok dari supplier, penjualan ke konsumen) hingga pencatatan perubahan tersebut di pembukuan. Saat ini proses ini berjalan manual:

1. Ada restok → operator menambah stok secara manual di catatan.
2. Ada penjualan → operator mengurangi stok secara manual.
3. Perubahan stok itu **tidak** otomatis tercatat di pembukuan (cashbook/pencatatan keuangan), sehingga owner harus menyalin ulang angka dari catatan stok ke catatan keuangan secara berkala (biasanya mingguan).

### 1.3 Sistem yang Sudah Ada
- **Sistem kasir (point-of-sale)** — mencatat penjualan, berbasis desktop/web milik toko.
- **Sistem pembukuan/keuangan** — spreadsheet (Excel/Google Sheet) yang dikelola owner untuk mencatat omzet, biaya, dan arus kas.
- **Catatan stok** — saat ini masih manual (buku/Excel), belum berupa layanan terpusat.

---

## 2. Persoalan Integrasi

Data stok tersebar di tiga tempat yang berbeda dan **tidak pernah sinkron otomatis**:

| Data | Lokasi saat ini | Siapa yang terdampak |
|---|---|---|
| Stok produk | Catatan manual / spreadsheet | Operator kasir, owner |
| Riwayat penjualan | Sistem kasir | Owner (untuk rekap) |
| Pencatatan keuangan | Spreadsheet pembukuan | Owner (untuk laporan laba rugi) |

**Akibatnya:**
- Stok fisik dan angka di pembukuan bisa beda jauh, owner baru sadar selisihnya saat tutup buku mingguan.
- Operator kasir tidak tahu apakah stok masih cukup untuk dijual tanpa cek manual.
- Tidak ada jejak *audit*: kalau stok berkurang, tidak jelas penyebabnya (penjualan? restok mundur? selisih?).

Dengan kata lain, data terfragmentasi menjadi **silo** di setiap sistem/catatan, dan tidak ada satu sumber kebenaran (single source of truth) untuk stok.

---

## 3. Pendekatan & Gaya Integrasi (pengembangan dari Tugas 3)

| Pasangan Sistem | Gaya Integrasi | Alasan / Trade-off |
|---|---|---|
| **Layanan Stok ↔ Sistem Kasir** | **Sinkron (REST)** | Kasir butuh tahu stok *real-time* untuk memvalidasi penjualan saat transaksi berjalan. Trade-off: layanan stok harus selalu siap dan latensi rendah; kalau down, kasir terdampak langsung. |
| **Layanan Stok ↔ Layanan Pembukuan** | **Asinkron (event via message broker)** | Pembukuan tidak butuh tahu stok detik itu juga — cukup tahu *perubahan* yang sudah terjadi, dan boleh diproses belakangan. Trade-off: ada *eventual consistency* (pembukuan bisa tertinggal beberapa detik dari stok), tapi sistem pembukuan tidak akan pernah menjadi titik kegagalan bagi transaksi penjualan. |
| **Layanan Stok ↔ UI Admin (frontend Inertia/React)** | **Sinkron (REST)** | UI hanya tampilan CRUD/monitoring, tidak masalah kalau ada jeda kecil. |

Pola ini sesuai hasil Tugas 3: **sinkron untuk kebutuhan konsistensi real-time (kasir), asinkron untuk kebutuhan "kirim kabar setelah terjadi" (pembukuan)**, memakai *outbox pattern* supaya event dan mutasi data stok tidak terpisah.

---

## 4. Rancangan Arsitektur

```
┌─────────────────────────┐          ┌──────────────────────────┐
│   Sistem Kasir (POS)     │──REST──▶│  Layanan Stok & Katalog  │
│   (sistem mitra yang ada)│          │  (Layanan 1 — dibangun   │
└─────────────────────────┘          │   di proyek ini, Laravel)│
                                     └───────────┬──────────────┘
                                                │ 1) tulis data stok
                                                │ 2) tulis outbox (meski event)
                                                │ 3) commit transaksi DB
                                                │
                                                ▼
                                     ┌──────────────────────────┐
                                     │   RabbitMQ (message      │
                                     │   broker) — exchange     │
                                     │   "toko.events" (topic)  │
                                     └───────────┬──────────────┘
                                                │ consume
                                                ▼
                                     ┌──────────────────────────┐
                                     │  Layanan Pembukuan       │
                                     │  (Layanan 2 — menerima    │
                                     │  event StokDisesuaikan)   │
                                     └──────────────────────────┘

   ┌─────────────────────────┐
   │ UI Admin (Inertia/React)│──REST──▶  Layanan Stok & Katalog (tambahan, bukan jalur utama)
   └─────────────────────────┘
```

> Catatan: untuk checkpoint ini, **Layanan Pembukuan cukup disimulasikan** sebagai consumer yang memverifikasi event masuk ke RabbitMQ (bisa berupa listener sederhana yang log/terima pesan di RabbitMQ Management UI), karena fokusnya membuktikan **jalur asinkron benar-benar lewat broker**, bukan membangun sistem pembukuan utuh.

### Batas Tanggung Jawab Tiap Layanan
1. **Layanan Stok & Katalog** (yang dibangun, Laravel + PHP):
   - Menangani CRUD produk, penyesuaian stok (sinkron dengan kasir via REST).
   - Menerbitkan event `StokDisesuaikan` ke RabbitMQ via outbox (asinkron ke pembukuan).
   - Tanggung jawab tunggal: *sumber kebenaran stok* + *mencatat event perubahan*.
2. **Layanan Pembukuan (konsumen)**:
   - Mengonsumsi event `StokDisesuaikan` dari RabbitMQ.
   - Mencatat perubahan ke pembukuan (penjualan = pendapatan, restok = biaya/penambahan aset).
   - Tanggung jawab tunggal: *mencatat transaksi keuangan dari perubahan stok*.
3. **UI Admin (opsional, sudah ada sebagai lapisan Inertia/React di repo)**:
   - Tampilan daftar produk, form penyesuaian stok.
   - Tidak menyimpan data sendiri, hanya meneruskan ke REST.

### Jalur Sinkron (REST)
- `GET/POST /api/v1/produk` — daftar/tambah produk.
- `GET /api/v1/produk/{sku}` — detail produk.
- `POST /api/v1/produk/{sku}/penyesuaian-stok` — ubah stok (dipakai kasir/UI).

### Jalur Asinkron (Event via Message Broker)
- Event: **StokDisesuaikan**
- Exchange: **`toko.events`** (topology: topic, durable)
- Routing key: **`stok.{alasan}`** (mis. `stok.penjualan`, `stok.restok`)
- Field payload: `sku`, `perubahan`, `alasan`, `stok_sesudah`, `harga`, `terjadi_pada`
- Penerbit: Layanan Stok & Katalog (via outbox pattern + `RabbitPublisher`)
- Penerima: Layanan Pembukuan

---

## Daftar Anggota & Pembagian Tugas

| Anggota | Bagian yang dikerjakan |
|---|---|
| [Nama 1] | Desain arsitektur + Layanan Stok (REST controller, model, outbox) |
| [Nama 2] | Implementasi event & RabbitMQ (publisher, exchange, routing key) |
| [Nama 3] | Feature test (API + outbox) + Dokumentasi (README, openapi.yaml) |

*(Ganti nama di atas dengan nama anggota kelompok kalian sebelum dikumpulkan.)*
