# Proyek Akhir — Layanan Stok & Katalog + Integrasi Asinkron (Warung Kelontong Pak Hendy)

Repositori ini memuat implementasi **Checkpoint 1 Proyek Akhir** mata kuliah Integrasi Sistem dan Komputasi Awan (25IF1504):

- **Studi Kasus Mitra:** **Warung Kelontong Pak Hendy** (usaha ritel kebutuhan pokok dan sembako).
- **Repository Git:** [https://github.com/asukarew/UTS](https://github.com/asukarew/UTS)
- **Spesifikasi API (Design-First):** [`openapi.yaml`](openapi.yaml) (OpenAPI 3.0.3, bersih dari galat Spectral, standar Problem Details RFC 7807 / RFC 9457).
- **Kontrak Event:** Tercantum di bawah dan di berkas terpisah [`docs/README-event.md`](docs/README-event.md).
- **Dokumen Analisis:** [`docs/analisis.md`](docs/analisis.md).

---

## Anggota Kelompok & Pembagian Tugas

| Nama | NIM | Peran & Bagian yang Dikerjakan |
|---|---|---|
| **Difki Junaedi** | 3224005 | REST API (controller, model, validasi, integrasi transaksi DB & outbox) |
| **Bima Azmi Khoerullah** | 3224017 | Event & RabbitMQ (publisher, konfigurasi exchange, topologi routing key) |
| **Nurul Hidayah** | 3224002 | Feature test (API & outbox) + Dokumentasi (OpenAPI Spectral, README, dokumen analisis) |

---

## Kontrak Event Asinkron

Jalur komunikasi asinkron antara **Layanan Stok & Katalog** dengan **Layanan Pembukuan** kas warung menggunakan event broker **RabbitMQ** dengan topologi *topic exchange*.

| Komponen | Spesifikasi Kontrak |
|---|---|
| **Nama Event** | `StokDisesuaikan` (fakta domain bentuk lampau yang sudah permanen di DB) |
| **Exchange** | `toko.events` (topologi `topic`, `durable: true`, non-auto-delete) |
| **Routing Key** | `stok.{alasan}` (contoh: `stok.penjualan`, `stok.restok`, `stok.koreksi`, `stok.retur`) |
| **Penerbit (Publisher)** | Layanan Stok & Katalog (`ProdukController@adjustStock` via `Outbox::catat` + `RabbitPublisher`) |
| **Penerima (Consumer)** | Layanan Pembukuan / Keuangan (mencatat mutasi persediaan & mutasi kas warung secara asinkron) |

### Field Payload Event (JSON Body)

| Field | Tipe Data | Wajib | Keterangan |
|---|---|---|---|
| `sku` | `string` | Ya | Stock Keeping Unit unik dari produk kelontong yang stoknya disesuaikan |
| `perubahan` | `integer` | Ya | Selisih stok: negatif (pengurangan/penjualan) atau positif (penambahan/restok) |
| `alasan` | `string` | Ya | Alasan perubahan (menjadi penentu segmen routing key `stok.{alasan}`) |
| `stok_sesudah` | `integer` | Ya | Total stok akhir produk setelah penyesuaian berhasil di-commit ke DB |
| `harga` | `integer` | Ya | Harga satuan produk dalam Rupiah (untuk kebutuhan valuasi pembukuan kas warung) |
| `terjadi_pada` | `string` | Ya | Waktu kejadian dalam format ISO 8601 / RFC 3339 |

Contoh payload event:
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

*Metadata AMQP:* `content_type: "application/json"`, `delivery_mode: 2` (persistent), `message_id: UUIDv4`, `type: "StokDisesuaikan"`.

---

## Spesifikasi API & Galat Problem Details (RFC 7807)

Spesifikasi API ditulis secara *design-first* di berkas [`openapi.yaml`](openapi.yaml). Spesifikasi ini valid terhadap aturan linter OpenAPI (Spectral).

Semua respons galat HTTP (`400`, `404`, `409`, `422`, `500`) menyertakan header `Content-Type: application/problem+json` dan struktur baku **RFC 7807 / RFC 9457**:

```json
{
  "type": "about:blank",
  "title": "Conflict",
  "status": 409,
  "detail": "Stock adjustment would result in negative stock.",
  "instance": "http://localhost:8090/api/v1/produk/MYK-001/penyesuaian-stok"
}
```

Untuk kesalahan validasi (`422 Unprocessable Entity`), ditambahkan atribut `invalid_params`:

```json
{
  "type": "about:blank",
  "title": "Validation Error",
  "status": 422,
  "detail": "The sku field is required.",
  "instance": "http://localhost:8090/api/v1/produk",
  "invalid_params": [
    {
      "name": "sku",
      "reason": "The sku field is required."
    }
  ]
}
```

---

## Prasyarat

- Docker & Docker Compose (untuk menjalankan Laravel Sail & RabbitMQ)
- PHP >= 8.3 & Composer (opsional di host, sudah tersedia di dalam container Sail)
- Node.js & npm (opsional di host, sudah tersedia di container Sail)

---

## Panduan Menjalankan Layanan dari Nol

### 1. Kloning Repositori & Salin Konfigurasi Lingkungan

```bash
git clone https://github.com/asukarew/UTS.git
cd UTS
cp .env.example .env
```

File `.env.example` sudah dilengkapi nilai bawaan untuk Laravel Sail (`APP_PORT=8090`, MySQL) dan RabbitMQ:
```ini
APP_PORT=8090
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=host.docker.internal
RABBITMQ_PORT=5672
RABBITMQ_VHOST=/
RABBITMQ_USER=xavyera
RABBITMQ_PASSWORD=bima
RABBITMQ_EXCHANGE=toko.events
```

### 2. Nyalakan Message Broker RabbitMQ

Jalankan script pengontrol container RabbitMQ (`rabbitmq:4-management`):
```bash
./rabit.sh on
```
- **Port AMQP:** `5672`
- **Management UI:** `http://localhost:15672` (Username: `xavyera`, Password: `bima`)

### 3. Bangun dan Jalankan Stack Laravel Sail

```bash
# Bangun dan nyalakan container aplikasi & MySQL
./vendor/bin/sail up -d

# Generate application key & jalankan migrasi database
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

*(Catatan: Jika Sail belum terinstal pada dependensi host, jalankan `docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html laravelsail/php84-composer:latest composer install --ignore-platform-reqs` sebelum menjalankan `./vendor/bin/sail up -d`)*

### 4. (Opsional) Jalankan Frontend Web UI

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

### 5. Verifikasi Endpoint API & Alur Asinkron

Base URL API: `http://localhost:8090/api/v1`

```bash
# 1. Tambah produk baru (POST /produk)
curl -X POST http://localhost:8090/api/v1/produk \
     -H "Content-Type: application/json" \
     -d '{"sku":"MYK-001","name":"Minyak Goreng Sawit 1L","harga":14000,"stock":20}'

# 2. Cek detail produk (GET /produk/{sku})
curl http://localhost:8090/api/v1/produk/MYK-001

# 3. Penyesuaian stok (POST /produk/{sku}/penyesuaian-stok)
#    Aksi ini memutasi DB + mencatat outbox + memancarkan event ke RabbitMQ
curl -X POST http://localhost:8090/api/v1/produk/MYK-001/penyesuaian-stok \
     -H "Content-Type: application/json" \
     -d '{"perubahan":-3,"alasan":"penjualan"}'

# 4. Uji galat Problem Details 409 Conflict (stok tidak boleh negatif)
curl -i -X POST http://localhost:8090/api/v1/produk/MYK-001/penyesuaian-stok \
     -H "Content-Type: application/json" \
     -d '{"perubahan":-100,"alasan":"penjualan"}'
```

Untuk memverifikasi event di RabbitMQ:
1. Buka `http://localhost:15672` (user: `xavyera`, pass: `bima`).
2. Masuk ke tab **Exchanges** → klik `toko.events`.
3. Pesan masuk dengan routing key `stok.penjualan` tercatat pada grafik lalu lintas pesan.

### 6. Menghentikan Layanan

```bash
# Matikan Laravel Sail
./stop.sh

# Matikan RabbitMQ
./rabit.sh off
```

---

## Menjalankan Feature Test

Feature test menguji keseluruhan endpoint REST API (termasuk validasi galat Problem Details RFC 7807) serta alur transaksi Outbox pattern.

```bash
# Menjalankan seluruh test suite
./vendor/bin/sail artisan test

# Menjalankan test API produk (termasuk verifikasi Problem Details 404, 409, 422)
./vendor/bin/sail artisan test --filter=ProdukApiTest

# Menjalankan test outbox & transaksi atomik
./vendor/bin/sail artisan test --filter=OutboxTest
```

Pengujian menggunakan trait `RefreshDatabase` dengan database pengujian terisolasi sehingga tidak memerlukan koneksi RabbitMQ hidup (koneksi Rabbit disimulasikan/di-mock).

---

## Verifikasi Linter OpenAPI (Spectral)

Untuk memastikan berkas `openapi.yaml` bebas dari galat/peringatan Spectral:

```bash
npx @stoplight/spectral-cli lint openapi.yaml
```

---

## Daftar Endpoint API

| Method | Endpoint | Status Sukses | Deskripsi |
|---|---|---|---|
| `GET` | `/api/v1/produk` | `200 OK` | Mengambil seluruh katalog produk kelontong |
| `POST` | `/api/v1/produk` | `201 Created` | Menambahkan produk baru ke katalog |
| `GET` | `/api/v1/produk/{sku}` | `200 OK` | Mengambil detail produk berdasarkan SKU |
| `POST` | `/api/v1/produk/{sku}/penyesuaian-stok` | `200 OK` | Menyesuaikan stok produk & menerbitkan event domain |

---

## Struktur File Proyek

```
UTS/
├── openapi.yaml                           # Spesifikasi OpenAPI 3.0.3 (Design-first, Problem Details, Spectral-clean)
├── README.md                              # Panduan instalasi dari nol, kontrak event, pembagian tugas
├── compose.yaml                           # Docker Compose untuk Laravel Sail & MySQL
├── rabit.sh                               # Skrip pengontrol RabbitMQ container (on/off)
├── stop.sh                                # Skrip penghenti container Laravel Sail
├── docs/
│   ├── analisis.md                        # Dokumen analisis integrasi mitra Warung Kelontong Pak Hendy
│   └── README-event.md                    # Dokumentasi lengkap kontrak event asinkron
├── app/
│   ├── Http/Controllers/Api/
│   │   └── ProdukController.php           # Endpoint REST + DB transaction + Outbox recorder
│   ├── Http/Middleware/
│   │   └── FormatJsonResponse.php         # JSON formatting & RFC 7807 content-type handler
│   ├── Events/
│   │   └── StokDisesuaikan.php            # Definisi event domain & payload generator
│   ├── Messaging/
│   │   └── RabbitPublisher.php            # AMQP Publisher ke exchange topic RabbitMQ
│   └── Models/
│       ├── Product.php                    # Model produk
│       ├── Produk.php                     # Model produk (alias bahasa Indonesia)
│       └── Outbox.php                     # Model tabel outbox (pola outbox transaksional)
└── tests/
    └── Feature/
        ├── ProdukApiTest.php              # Feature test REST API & respons galat RFC 7807
        └── OutboxTest.php                 # Feature test pencatatan outbox & atomisitas transaksi
```
