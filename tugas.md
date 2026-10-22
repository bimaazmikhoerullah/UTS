# Tugas yang Telah Ditambahkan

## 1. REST API dengan Laravel (Materi P04: Merancang, Mendokumentasikan, dan Menguji REST API)

### a. Struktur Database
- Migrasi: `database/migrations/2026_10_18_000001_create_products_table.php`
  - Tabel `products` dengan kolom: `id`, `sku` (unique), `name`, `stock`, `timestamps`

### b. Model
- `app/Models/Product.php`
  - Mass assignable: `sku`, `name`, `stock`
  - Casts: `stock` ke integer

### c. Controller
- `app/Http/Controllers/Api/ProdukController.php`
  - Metode:
    - `index`: daftar produk (GET /api/v1/produk)
    - `store`: tambah produk (POST /api/v1/produk)
    - `show`: detail produk by SKU (GET /api/v1/produk/{sku})
    - `adjustStock`: sesuaikan stok (POST /api/v1/produk/{sku}/penyesuaian-stok)

### d. Rute API
- `routes/api.php`
  - Prefix `/api/v1`
  - Resource `produk` dengan hanya `index`, `store`, `show`
  - Rute custom: `POST produk/{sku}/penyesuaian-stok` → `ProdukController@adjustStock`

### e. Bootstrap
- `bootstrap/app.php`
  - Ditambahkan `api: __DIR__.'/../routes/api.php',` ke dalam `withRouting`
  - Ditambahkan `->withProviders()` untuk memastikan penyedia layanan dasar dimuat

### f. Dokumentasi API
- `openapi.yaml` (di root proyek)
  - OpenAPI 3.0 specification untuk empat endpoint
  - Mendefinisikan skema Product, ProductInput, StockAdjustment, ErrorResponse
  - Termasuk respons Problem Details untuk kesalahan

### g. Uji Fitur
- `tests/Feature/ProdukApiTest.php`
  - Uji semua endpoint termasuk kasus sukses dan kesalahan
  - Menggunakan RefreshDatabase untuk mengisolasi uji
  - Memvalidasi respon JSON, kode status, dan header

## 2. Persiapan Integrasi Asinkron dengan RabbitMQ (Materi P05: Integrasi Asinkron dengan Message Broker)

### a. Paket yang Ditambahkan
- `composer require vladimir-yuldashev/laravel-queue-rabbitmq:^15`
- `composer require php-amqplib/php-amqplib:^3.7`

### b. Konfigurasi Antrian
- `config/queue.php`
  - Ditambahkan koneksi `rabbitmq` di bawah array `connections`:
    ```php
    'rabbitmq' => [
        'driver' => 'rabbitmq',
        'hosts' => [
            [
                'host' => env('RABBITMQ_HOST', '127.0.0.1'),
                'port' => env('RABBITMQ_PORT', 5672),
                'vhost' => env('RABBITMQ_VHOST', '/'),
                'user' => env('RABBITMQ_LOGIN', 'guest'),
                'password' => env('RABBITMQ_PASSWORD', 'guest'),
            ],
        ],
        'queue' => env('RABBITMQ_QUEUE', 'default'),
        'exchange' => env('RABBITMQ_EXCHANGE', ''),
        'routing_key' => env('RABBITMQ_ROUTING_KEY', 'default'),
        'retry_after' => (int) env('RABBITMQ_RETRY_AFTER', 90),
        'after_commit' => false,
    ],
    ```
  - Pastikan variabel lingkungan ditambahkan ke `.env` (lihat di bawah)

### c. Variabel Lingkungan (ditambahkan ke `.env`)
- `RABBITMQ_HOST=127.0.0.1`
- `RABBITMQ_PORT=5672`
- `RABBITMQ_VHOST=/`
- `RABBITMQ_LOGIN=guest`
- `RABBITMQ_PASSWORD=guest`
- `RABBITMQ_QUEUE=default`
- `RABBITMQ_EXCHANGE=`
- `RABBITMQ_ROUTING_KEY=default`
- `QUEUE_CONNECTION=rabbitmq` (diubah dari `database` ke `rabbitmq` untuk menggunakan RabbitMQ sebagai koneksi antrian default)

### d. Server RabbitMQ
- Untuk menjalankan RabbitMQ, gunakan Docker:
  ```bash
  docker run -d --hostname my-rabbit --name some-rabbit -p 5672:5672 -p 15672:15672 rabbitmq:3-management
  ```
  - Akses UI management di `http://localhost:15672` dengan pengguna/kata sandi `guest`/`guest`

### e. Langkah Selanjutnya
- Membuat event (misal: `StokDisesuaikan`)
- Membuat listener yang mendengarkan event dan menerbitkan pesan ke RabbitMQ
- Membuat job yang diproses oleh konsumen RabbitMQ (misal: sinkronisasi stok ke layanan pembukuan atau notifikasi)
- Mengonfigurasi Laravel Queue untuk menggunakan koneksi rabbitmq
- Menjalankan worker: `php artisan queue:work --queue=rabbitmq --daemon`

## Catatan
- Semua uji fitur untuk REST API dapat dijalankan dengan:
  ```bash
  vendor/bin/phpunit --filter=ProdukApiTest
  ```
  (pastikan phpunit terinstall sebagai dependensi dev; jika belum, jalankan `composer require --dev phpunit/phpunit ^10`)

- Pastikan kunci aplikasi di `.env` sudah diisi (generate dengan `php artisan key:generate`) sebelum menjalankan uji atau server.

---
Terima kasih, Bos. Tugas selesai ditambahkan. Mau lanjut ke implementasi integrasi asinkron atau uji coba API lagi? Gua siap, Bos. Babat sampai kelar. Gas!