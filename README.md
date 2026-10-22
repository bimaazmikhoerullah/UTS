# Proyek Akhir — Layanan Stok & Katalog + Integrasi Asinkron (Toko Berkah Jaya)

Proyek ini berisi:
- **Layanan REST** (Laravel) untuk katalog & penyesuaian stok — jalur **sinkron**.
- **Publisher event** ke **RabbitMQ** (topologi *topic*) untuk jalur **asinkron**, memakai pola *outbox* supaya mutasi stok dan pencatatan event selalu dalam satu transaksi.
- **Feature test** untuk API dan outbox.
- Dokumentasi analisis di [`docs/analisis.md`](docs/analisis.md) dan kontrak event di [`docs/README-event.md`](docs/README-event.md).
- Spesifikasi API (design-first) di [`openapi.yaml`](openapi.yaml).

## Prasyarat

- Docker (untuk Laravel Sail & RabbitMQ)
- PHP & composer (sudah di-bundel di image Sail)
- Node.js (untuk asset Inertia/React)

## Menjalankan dari Nol

### 1. Salin file lingkungan
```bash
cp .env.example .env
php artisan key:generate
```
Pastikan `.env` sudah berisi konfigurasi RabbitMQ (sesuaikan dengan kredensial container RabbitMQ kalian):
```
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=host.docker.internal
RABBITMQ_PORT=5672
RABBITMQ_VHOST=/
RABBITMQ_USER=<username>
RABBITMQ_PASSWORD=<password>
RABBITMQ_QUEUE=default
```

### 2. Nyalakan RabbitMQ
```bash
./rabit.sh on
```
Ini menjalankan container RabbitMQ (`rabbitmq:4-management`) di port 5672 (AMQP) dan 15672 (Management UI). Login Management UI: `http://localhost:15672` dengan user/pass sesuai kredensial di atas.

### 3. Bangun & jalankan stack Laravel
```bash
./vendor/bin/sail build
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
```

### 4. (Opsional) Jalankan web UI Inertia
```bash
./vendor/bin/sail npm run dev
```

### 5. Demo cepat & verifikasi jalur asinkron
```bash
# Tambah produk
curl -X POST http://localhost:8090/api/v1/produk \
     -H "Content-Type: application/json" \
     -d '{"sku":"KRD-001","name":"Kerudung Paris Polos","harga":45000,"stock":10}'

# Penyesuaian stok (memancarkan event StokDisesuaikan ke RabbitMQ)
curl -X POST http://localhost:8090/api/v1/produk/KRD-001/penyesuaian-stok \
     -H "Content-Type: application/json" \
     -d '{"perubahan":-3,"alasan":"penjualan"}'

# Cek RabbitMQ Management UI: http://localhost:15672
# Exchange "toko.events" (topologi topic, durable) akan terlihat di tab Exchanges,
# dengan routing key "stok.penjualan" terikat ke queue-nya.
```

### 6. Stop semua container
```bash
./stop.sh
```
(RabbitMQ juga dimatikan dengan `./rabit.sh off`.)

## Menjalankan Feature Test

```bash
./vendor/bin/sail artisan test
```
Atau fokus ke test API dan outbox:
```bash
./vendor/bin/sail artisan test --filter=ProdukApiTest
./vendor/bin/sail artisan test --filter=OutboxTest
```
Test dijalankan lewat `RefreshDatabase` (SQLite di `.env.testing` biasanya, atau sesuai `phpunit.xml`) — tidak perlu MySQL/RabbitMQ nyata untuk test, karena publisher Rabbit di-mock/disimulasikan saat test (lihat `tests/Feature/OutboxTest.php`).

## Endpoint API

Base URL (ketika berjalan lewat Laravel Sail): `http://localhost:8090/api/v1`
(Sail memetakan port 80 di container ke port 8090 di host lewat `APP_PORT`. Kalau Anda menjalankan lewat `php artisan serve` langsung tanpa container, pakai `http://localhost:8000/api/v1`.)

| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/produk` | Daftar produk |
| `POST` | `/produk` | Tambah produk |
| `GET` | `/produk/{sku}` | Detail produk |
| `POST` | `/produk/{sku}/penyesuaian-stok` | Sesuaikan stok (positif/negatif) |

Contoh quick test dengan `curl` (pakai base URL yang benar sesuai section "Endpoint API" di atas — biasanya `http://localhost:8090` saat pakai Sail):
```bash
curl -X POST http://localhost:8090/api/v1/produk \
     -H "Content-Type: application/json" \
     -d '{"sku":"KRD-001","name":"Kerudung Paris Polos","harga":45000,"stock":10}'

curl -X POST http://localhost:8090/api/v1/produk/KRD-001/penyesuaian-stok \
     -H "Content-Type: application/json" \
     -d '{"perubahan":-3,"alasan":"penjualan"}'
```
Setiap penyesuaian stok yang berhasil akan:
1. Update stok di DB (transaksi).
2. Menulis 1 baris ke tabel `outbox` (routing key `stok.{alasan}`).
3. (Lewat publisher) menerbitkan event `StokDisesuaikan` ke RabbitMQ exchange `toko.events` — lihat [`docs/README-event.md`](docs/README-event.md) untuk detail kontrak event.

## Struktur Kode Utama

| File | Peran |
|---|---|
| `app/Http/Controllers/Api/ProdukController.php` | REST endpoint + logika transaksi stok + catat outbox |
| `app/Events/StokDisesuaikan.php` | Definisi event (kontrak payload) |
| `app/Messaging/RabbitPublisher.php` | Kirim pesan ke RabbitMQ |
| `app/Models/Outbox.php` | Outbox pattern — catat event dalam transaksi DB |
| `tests/Feature/ProdukApiTest.php` | Test REST API |
| `tests/Feature/OutboxTest.php` | Test outbox + event |

## Anggota Kelompok & Pembagian Tugas

| Anggota | Bagian |
|---|---|
| [Nama 1] | REST API (controller, model, outbox) |
| [Nama 2] | Event + RabbitMQ (publisher, konfigurasi exchange/routing key) |
| [Nama 3] | Feature test + Dokumentasi (openapi.yaml, README, analisis) |

> Ganti nama di atas dengan nama anggota kelompok kalian sebelum submit. Riwayat commit di repo ini seharusnya mencerminkan kontribusi masing-masing anggota (D2 di rubrik).

## Mengumpulkan untuk Checkpoint 1

1. Salin tautan repositori ini.
2. Export `docs/analisis.md` ke PDF (4–8 halaman) — bisa pakai `pandoc docs/analisis.md -o analisis.pdf` atau buka di editor lalu Print → Save as PDF.
3. Submit kedua file lewat Google Classroom sesuai tenggat.
