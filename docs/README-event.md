# Kontrak Event Asinkron — Warung Kelontong Pak Hendy

Dokumentasi ini merinci kontrak pesan asinkron yang digunakan pada sistem terintegrasi **Warung Kelontong Pak Hendy**, menggunakan message broker **RabbitMQ** dengan topologi *topic exchange*.

---

## Ringkasan Kontrak Event

| Komponen Kontrak | Nilai Spesifikasi |
|---|---|
| **Nama Event** | `StokDisesuaikan` |
| **Exchange** | `toko.events` (Type: `topic`, Durable: `true`, Auto-Delete: `false`) |
| **Routing Key** | `stok.{alasan}` (Format dinamis berbasis penyebab mutasi stok kelontong/sembako) |
| **Format Serialisasi** | JSON (`Content-Type: application/json`) |
| **Penerbit (Publisher)** | Layanan Stok & Katalog (Layanan 1 — Laravel via Outbox Pattern) |
| **Penerima (Consumer)** | Layanan Pembukuan / Keuangan (Layanan 2 — pencatat mutasi keuangan kas warung) |

---

## 1. Definisi & Semantik Event

- **Nama Event:** `StokDisesuaikan`
- **Bentuk Nama:** Menggunakan bentuk lampau (*past participle / fakta domain*) karena event menyatakan kejadian bisnis yang **sudah terjadi** dan sudah di-commit secara permanen ke basis data.
- **Trigger:** Dipancarkan setiap kali terjadi mutasi stok yang valid melalui endpoint sinkron `POST /api/v1/produk/{sku}/penyesuaian-stok`.

---

## 2. Topologi Broker (Exchange & Routing Key)

- **Exchange Name:** `toko.events`
  - **Type:** `topic` (memungkinkan routing fleksibel berbasis pola wildcard `stok.*` atau `stok.#`).
  - **Durable:** `true` (definisi exchange tetap tersimpan meski broker restart).
  - **Auto-Delete:** `false`.
- **Routing Key Pattern:** `stok.{alasan}`
  - Nilai `{alasan}` diambil dari atribut payload event.
  - **Daftar Routing Key yang Valid:**
    - `stok.penjualan` : Berkurangnya stok barang kelontong akibat transaksi penjualan harian ke pembeli.
    - `stok.restok` : Bertambahnya stok akibat pasokan/kulakan barang dari distributor/agen sembako.
    - `stok.koreksi` : Penyesuaian manual akibat stock opname / koreksi selisih hitung fisik di rak warung.
    - `stok.retur` : Bertambahnya stok akibat pengembalian barang dari pembeli atau retur ke supplier.
    - `stok.rusak` : Pengurangan stok akibat barang kadaluwarsa, kemasan bocor, atau rusak.

---

## 3. Struktur Header & Payload Pesan

### 3.1 Metadata / AMQP Properties

| Header / Property | Tipe | Nilai / Format | Keterangan |
|---|---|---|---|
| `content_type` | `string` | `application/json` | Format serialisasi data |
| `delivery_mode` | `integer` | `2` (Persistent) | Pesan disimpan ke disk broker |
| `message_id` | `string` | UUID v4 (contoh: `d3b07384-d113-4ec6-8968-30c1844bdf92`) | ID unik pesan untuk idempotensi consumer |
| `type` | `string` | `StokDisesuaikan` | Tipe event domain |
| `timestamp` | `integer` | Unix Timestamp | Waktu publikasi AMQP |

### 3.2 Payload (Body Pesan)

| Field | Tipe Data | Wajib | Keterangan & Aturan Nilai |
|---|---|---|---|
| `sku` | `string` | Ya | Stock Keeping Unit unik dari produk kelontong yang dimutasi (contoh: `"MYK-001"`). |
| `perubahan` | `integer` | Ya | Selisih unit stok: nilai negatif (pengurangan) atau positif (penambahan). |
| `alasan` | `string` | Ya | Penyebab perubahan stok (`"penjualan"`, `"restok"`, dll). |
| `stok_sesudah` | `integer` | Ya | Jumlah total stok akhir produk setelah penyesuaian berhasil disimpan ke database. |
| `harga` | `integer` | Ya | Harga satuan produk dalam Rupiah (Rp) untuk kalkulasi pembukuan/akuntansi warung. |
| `terjadi_pada` | `string` | Ya | Waktu pencatatan mutasi dalam format ISO 8601 / RFC 3339 dengan timezone offset. |

### 3.3 Contoh JSON Payload

```json
{
  "sku": "MYK-001",
  "perubahan": -3,
  "alasan": "penjualan",
  "stok_sesudah": 7,
  "harga": 14000,
  "terjadi_pada": "2026-10-24T14:30:00+07:00"
}
```

---

## 4. Peran Penerbit & Penerima

### 4.1 Penerbit (Publisher): Layanan Stok & Katalog
- **Sumber Kode:** `app/Http/Controllers/Api/ProdukController.php`, `app/Events/StokDisesuaikan.php`, `app/Messaging/RabbitPublisher.php`.
- **Jaminan Konsistensi (Outbox Pattern):**
  Untuk mencegah inkonsistensi (*dual-write problem*), perubahan stok pada tabel `products` dan pencatatan event pada tabel `outbox` dibungkus dalam **satu transaksi database ACID** (`DB::transaction`).
  Jika database gagal menyimpan data stok, event tidak akan tercatat di outbox. Sebaliknya jika broker RabbitMQ sedang offline, data transaksi tetap aman dan outbox dapat dikirim ulang secara berkala (*at-least-once delivery*).

### 4.2 Penerima (Consumer): Layanan Pembukuan / Keuangan
- **Peran Bisnis:**
  - Menangkap event `StokDisesuaikan` secara asinkron tanpa memperlambat proses transaksi di kasir warung.
  - Untuk routing key `stok.penjualan`: mencatat transaksi penjualan ke buku kas warung (penerimaan kas harian, pencatatan pendapatan dan HPP).
  - Untuk routing key `stok.restok`: mencatat penambahan stok sembako serta pengeluaran kas kulakan ke agen.
- **Idempotensi Consumer:**
  Consumer wajib memeriksa `message_id` (UUID v4) untuk memastikan pesan yang sama tidak diproses dua kali (*idempotent consumer*).
- **Simulasi pada Checkpoint:**
  Pada pengujian checkpoint ini, penerimaan event disimulasikan melalui antrean pada RabbitMQ Management UI (`http://localhost:15672`).

---

## 5. Cara Verifikasi Penerbitan Event di RabbitMQ

1. Nyalakan container RabbitMQ:
   ```bash
   ./rabit.sh on
   ```
2. Buka RabbitMQ Management UI di browser:
   - URL: `http://localhost:15672`
   - Kredensial: Username `xavyera`, Password `bima`
3. Lakukan penyesuaian stok melalui curl atau REST client:
   ```bash
   curl -X POST http://localhost:8090/api/v1/produk/MYK-001/penyesuaian-stok \
        -H "Content-Type: application/json" \
        -d '{"perubahan": -3, "alasan": "penjualan"}'
   ```
4. Di RabbitMQ Management:
   - Buka tab **Exchanges** → klik `toko.events`.
   - Lihat statistik *Message rates* masuk dengan routing key `stok.penjualan`.
   - Binding queue dapat memeriksa payload JSON yang diterbitkan.
