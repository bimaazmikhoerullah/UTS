# 🗄️ Database & Event Schema Specification — Warung Pak Hendy

Dokumen ini menjelaskan struktur perancangan basis data relasional (**PostgreSQL**) pada layanan **Inventory Backend Service**, serta struktur skema data untuk _Domain Event_ pada **Message Broker (RabbitMQ)** untuk proyek **Warung Pak Hendy** (Kelompok 67).

## 🛢️ 1. Entity Relationship Diagram (ERD) Basis Data

Secara konseptual, basis data `inventory_db` mengelola empat entitas utama untuk transaksi penjualan dan pemantauan stok:

```
+-------------------+       +-----------------------+       +-------------------------+
|     produks       |       |    transaksi_items    |       |       transaksis        |
+-------------------+       +-----------------------+       +-------------------------+
| PK sku (VARCHAR)  |<──┐   | PK id (BIGINT)        |   ┌──>| PK nomor_transaksi (VAR)|
|    nama (VARCHAR) |   └───| FK sku (VARCHAR)      |   │   |    kasir_id (BIGINT)     |
|    kategori (VAR) |       | FK nomor_transaksi────|───┘   |    metode_bayar (VAR)   |
|    harga (INT)    |       |    jumlah_beli (INT)  |       |    subtotal (INT)       |
|    stok (INT)     |       |    harga_satuan (INT) |       |    total_bayar (INT)    |
|    stok_min (INT) |       |    subtotal (INT)     |       |    kembalian (INT)      |
|    created_at     |       +-----------------------+       |    created_at           |
|    updated_at     |                                       +-------------------------+
+-------------------+
```

## 📋 2. Spesifikasi Tabel Basis Data (PostgreSQL)

### A. Tabel `produks`

Menyimpan master data barang/produk yang dijual di Warung Pak Hendy beserta batas stok minimum.

|   |   |   |   |
|---|---|---|---|
|**Nama Kolom**|**Tipe Data**|**Atribut**|**Keterangan / Aturan Validasi**|
|`sku`|`VARCHAR(20)`|**PK**, Not Null|Format unik barang (misal: `BRG-001`).|
|`nama`|`VARCHAR(255)`|Not Null|Nama lengkap produk.|
|`kategori`|`VARCHAR(50)`|Not Null|Kategori barang (`sembako`, `minuman`, `bumbu`).|
|`harga`|`INTEGER`|Not Null, Min: 0|Harga jual per unit (dalam Rupiah).|
|`stok`|`INTEGER`|Not Null, Min: 0|Sisa kuantitas fisik barang saat ini.|
|`stok_minimum`|`INTEGER`|Not Null, Default: 5|Batas minimum pemicu _restock_ asinkron.|
|`created_at`|`TIMESTAMP`|Nullable|Tanggal dibuat.|
|`updated_at`|`TIMESTAMP`|Nullable|Tanggal terakhir stok/harga diperbarui.|

### B. Tabel `transaksis`

Menyimpan data header transaksi penjualan dari aplikasi POS Kasir.

|   |   |   |   |
|---|---|---|---|
|**Nama Kolom**|**Tipe Data**|**Atribut**|**Keterangan / Aturan Validasi**|
|`nomor_transaksi`|`VARCHAR(50)`|**PK**, Not Null|Format unik: `TRX-YYYYMMDD-XXX`.|
|`kasir_id`|`BIGINT`|Not Null|ID pengguna kasir yang bertugas.|
|`metode_pembayaran`|`VARCHAR(20)`|Not Null|`tunai`, `qris`, `debit`.|
|`subtotal`|`INTEGER`|Not Null|Total harga sebelum diskon.|
|`total_bayar`|`INTEGER`|Not Null|Nominal uang yang diterima dari pembeli.|
|`kembalian`|`INTEGER`|Not Null|Nominal kembalian transaksi.|
|`created_at`|`TIMESTAMP`|Not Null|Waktu persetujuan transaksi (_commit time_).|

### C. Tabel `transaksi_items`

Menyimpan rincian barang belanjaan untuk setiap transaksi (_junction table_).

|   |   |   |   |
|---|---|---|---|
|**Nama Kolom**|**Tipe Data**|**Atribut**|**Keterangan / Aturan Validasi**|
|`id`|`BIGSERIAL`|**PK**, Not Null|Auto-increment primary key.|
|`nomor_transaksi`|`VARCHAR(50)`|**FK**, Not Null|Terhubung ke `transaksis.nomor_transaksi`.|
|`sku`|`VARCHAR(20)`|**FK**, Not Null|Terhubung ke `produks.sku`.|
|`jumlah_beli`|`INTEGER`|Not Null, Min: 1|Jumlah barang yang dibeli.|
|`harga_satuan`|`INTEGER`|Not Null|Harga per unit saat transaksi berlangsung.|
|`subtotal`|`INTEGER`|Not Null|`jumlah_beli` $\times$ `harga_satuan`.|

### D. Tabel `processed_events` (Sisi Consumer Supplier Service)

Tabel khusus di sisi _Supplier Order Service_ untuk menjamin sifat **Idempotent Consumer**.

|   |   |   |   |
|---|---|---|---|
|**Nama Kolom**|**Tipe Data**|**Atribut**|**Keterangan**|
|`event_id`|`UUID`|**PK**, Not Null|UUID unik dari pesan RabbitMQ yang telah sukses diolah.|
|`nama_event`|`VARCHAR(100)`|Not Null|Contoh: `StockThresholdReached`.|
|`processed_at`|`TIMESTAMP`|Not Null|Tanggal & waktu pesan dieksekusi.|

## 🐘 3. Laravel Migration Code (`database/migrations`)

```
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Skema Tabel Produks
        Schema::create('produks', function (Blueprint $table) {
            $table->string('sku', 20)->primary();
            $table->string('nama');
            $table->string('kategori', 50);
            $table->unsignedInteger('harga');
            $table->unsignedInteger('stok');
            $table->unsignedInteger('stok_minimum')->default(5);
            $table->timestamps();
        });

        // 2. Skema Tabel Transaksis
        Schema::create('transaksis', function (Blueprint $table) {
            $table->string('nomor_transaksi', 50)->primary();
            $table->unsignedBigInteger('kasir_id');
            $table->string('metode_pembayaran', 20);
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('total_bayar');
            $table->unsignedInteger('kembalian');
            $table->timestamp('created_at')->useCurrent();
        });

        // 3. Skema Tabel Transaksi Items
        Schema::create('transaksi_items', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_transaksi', 50);
            $table->string('sku', 20);
            $table->unsignedInteger('jumlah_beli');
            $table->unsignedInteger('harga_satuan');
            $table->unsignedInteger('subtotal');

            $table->foreign('nomor_transaksi')->references('nomor_transaksi')->on('transaksis')->onDelete('cascade');
            $table->foreign('sku')->references('sku')->on('produks')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_items');
        Schema::dropIfExists('transaksis');
        Schema::dropIfExists('produks');
    }
};
```

## 📩 4. Event Schema Definition (RabbitMQ JSON Schema)

Berikut adalah aturan struktur payload event yang dikirimkan melalui broker:

```
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "title": "StockThresholdReachedEvent",
  "type": "object",
  "required": ["event_id", "nama_event", "waktu_kejadian", "data"],
  "properties": {
    "event_id": {
      "type": "string",
      "format": "uuid"
    },
    "nama_event": {
      "type": "string",
      "enum": ["StockThresholdReached"]
    },
    "waktu_kejadian": {
      "type": "string",
      "format": "date-time"
    },
    "data": {
      "type": "object",
      "required": ["sku", "nama_produk", "sisa_stok", "stok_minimum"],
      "properties": {
        "sku": { "type": "string" },
        "nama_produk": { "type": "string" },
        "sisa_stok": { "type": "integer" },
        "stok_minimum": { "type": "integer" },
        "rekomendasi_jumlah_restock": { "type": "integer" }
      }
    }
  }
}
```