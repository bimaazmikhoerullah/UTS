# 📡 REST API Specification — Warung Pak Hendy

Dokumen ini adalah spesifikasi kontrak **REST API** resmi untuk integrasi antara **Aplikasi Kasir (POS / Point of Sale)** dan **Backend Core & Database Inventori (Inventory Backend Service)** pada proyek **Warung Pak Hendy** (Kelompok 67).

Komunikasi ini dirancang menggunakan gaya arsitektur **Remote Procedure Invocation (RPI)** berbasis HTTP/JSON secara sinkron (_blocking_) dengan pendekatan **Design-First** berbasis **OpenAPI 3.1** serta memenuhi tingkat **Richardson Maturity Model Level 2**.

## 🌐 1. Informasi Dasar & Konfigurasi Umum

- **Base URL:** `http://localhost/api/v1`
    
- **Format Pertukaran Data:** `application/json`
    
- **Format Galat Baku:** `application/problem+json` (RFC 9457)
    
- **Zona Waktu / Format Tanggal:** ISO 8601 (`YYYY-MM-DDTHH:mm:ssZ` / `+07:00`)
    
- **Mata Uang:** Rupiah (IDR) disajikan dalam tipe data _integer_ tanpa angka desimal (contoh: `19500` untuk Rp19.500).
    

## 🛠️ 2. Ringkasan Matriks Endpoint

|   |   |   |   |   |   |
|---|---|---|---|---|---|
|**Method**|**Endpoint**|**Fungsi & Peran Bisnis**|**Respon Berhasil**|**Potensi Respon Galat**|**Sifat Operasi**|
|**GET**|`/produk`|Mengambil seluruh katalog produk dan harga terkini|`200 OK`|`500 Internal Error`|Safe, Idempotent|
|**GET**|`/produk/{sku}`|Mengambil detail 1 produk spesifik berdasarkan SKU|`200 OK`|`404 Not Found`|Safe, Idempotent|
|**POST**|`/produk`|Menginput barang/item produk baru ke inventori|`201 Created`|`400`, `422 Unprocessable`|Non-idempotent|
|**POST**|`/transaksi`|Memproses transaksi penjualan & pembaruan stok otomatis|`201 Created`|`400`, `409 Conflict`, `422`|Non-idempotent|

## 📋 3. Detail Lengkap Spesifikasi Endpoint

### 3.1. Menampilkan Daftar Katalog Produk

Menampilkan seluruh daftar barang yang terdaftar di sistem inventori beserta informasi harga dan sisa stok. Digunakan saat aplikasi POS melakukan inisialisasi awal atau _refresh_ katalog.

- **Method & Path:** `GET /v1/produk`
    
- **Headers:**
    
    - `Accept: application/json`
        
- **Query Parameters (Opsional):**
    
    - `kategori` _(string)_: Menyaring berdasarkan kategori barang (contoh: `sembako`, `minuman`).
        
    - `cari` _(string)_: Pencarian nama produk atau kode SKU.
        
- **Respons `200 OK`:**
    

```
{
  "status": "sukses",
  "total_data": 2,
  "data": [
    {
      "sku": "BRG-001",
      "nama": "Minyak Goreng Bimoli 1L",
      "kategori": "sembako",
      "harga": 19500,
      "stok": 30,
      "stok_minimum": 5,
      "tersedia": true,
      "diperbarui_pada": "2026-11-01T08:00:00+07:00"
    },
    {
      "sku": "BRG-002",
      "nama": "Gula Pasir Gulaku 1kg",
      "kategori": "sembako",
      "harga": 17500,
      "stok": 4,
      "stok_minimum": 10,
      "tersedia": true,
      "diperbarui_pada": "2026-11-01T09:30:00+07:00"
    }
  ]
}
```

### 3.2. Menampilkan Detail Satu Produk

Mencari dan menampilkan detail spesifik suatu barang berdasarkan kode _Stock Keeping Unit_ (SKU).

- **Method & Path:** `GET /v1/produk/{sku}`
    
- **URL Path Parameter:**
    
    - `sku` _(string, wajib)_: Kode unik barang berformat `BRG-XXX` (Pola Regex: `^[A-Z]{3}-[0-9]{3}$`).
        
- **Headers:**
    
    - `Accept: application/json`
        
- **Respons `200 OK`:**
    

```
{
  "status": "sukses",
  "data": {
    "sku": "BRG-001",
    "nama": "Minyak Goreng Bimoli 1L",
    "kategori": "sembako",
    "harga": 19500,
    "stok": 30,
    "stok_minimum": 5,
    "tersedia": true,
    "diperbarui_pada": "2026-11-01T08:00:00+07:00"
  }
}
```

### 3.3. Menambahkan Produk Baru ke Inventori

Memasukkan item produk baru ke dalam database backend. Biasanya diakses oleh admin inventori atau pemilik warung.

- **Method & Path:** `POST /v1/produk`
    
- **Headers:**
    
    - `Content-Type: application/json`
        
    - `Accept: application/json`
        
- **Request Body Payload:**
    

```
{
  "sku": "BRG-003",
  "nama": "Kecap Manis Bango 520ml",
  "kategori": "bumbu",
  "harga": 24000,
  "stok": 15,
  "stok_minimum": 5
}
```

- **Respons `201 Created`:**
    
    - **Header Respons:** `Location: http://localhost/api/v1/produk/BRG-003`
        
    - **Body Respons:**
        

```
{
  "status": "sukses",
  "pesan": "Produk berhasil ditambahkan ke inventori.",
  "data": {
    "sku": "BRG-003",
    "nama": "Kecap Manis Bango 520ml",
    "kategori": "bumbu",
    "harga": 24000,
    "stok": 15,
    "stok_minimum": 5,
    "tersedia": true,
    "diperbarui_pada": "2026-11-01T11:30:00+07:00"
  }
}
```

### 3.4. Mengirim Transaksi Penjualan (POS ke Backend Core)

Aplikasi Kasir (POS) mengirimkan data transaksi Penjualan untuk diproses oleh Backend Inventori. Backend akan memvalidasi item, mengunci baris DB (_pessimistic locking_), memotong jumlah stok, dan menyimpan riwayat transaksi.

- **Method & Path:** `POST /v1/transaksi`
    
- **Headers:**
    
    - `Content-Type: application/json`
        
    - `Accept: application/json`
        
- **Request Body Payload:**
    

```
{
  "nomor_transaksi": "TRX-20261101-001",
  "kasir_id": 2,
  "metode_pembayaran": "tunai",
  "items": [
    {
      "sku": "BRG-001",
      "jumlah_beli": 2,
      "harga_satuan": 19500
    },
    {
      "sku": "BRG-002",
      "jumlah_beli": 1,
      "harga_satuan": 17500
    }
  ],
  "subtotal": 56500,
  "total_bayar": 60000,
  "kembalian": 3500
}
```

- **Respons `201 Created` (Berhasil):**
    
    - **Header Respons:** `Location: http://localhost/api/v1/transaksi/TRX-20261101-001`
        
    - **Body Respons:**
        

```
{
  "status": "sukses",
  "nomor_transaksi": "TRX-20261101-001",
  "pesan": "Transaksi berhasil dicatat dan stok inventori telah diperbarui.",
  "waktu_proses": "2026-11-01T11:32:00+07:00",
  "ringkasan_stok": [
    {
      "sku": "BRG-001",
      "sisa_stok": 28
    },
    {
      "sku": "BRG-002",
      "sisa_stok": 3
    }
  ]
}
```

- **⚡ Efek Samping Asinkron (Side-Effect System):** Setelah transaksi sukses dicatat ke Database PostgreSQL, sistem akan memeriksa apakah stok produk berada di bawah atau sama dengan `stok_minimum`.
    
    - Contoh: Produk `BRG-002` kini sisa stoknya **3** (dibawah `stok_minimum` yang bernilai **10**).
        
    - Backend Laravel secara otomatis akan memancarkan _Domain Event_ asinkron **`StockThresholdReached`** ke RabbitMQ Exchange (`inventory.events`) agar diproses oleh **Supplier Order Service**.
        

## ⚠️ 4. Standar Format Penanganan Galat (RFC 9457 Problem Details)

Semua galat yang terjadi pada API **TIDAK BOLEH** dikembalikan menggunakan kode `200 OK` dengan status palsu. Seluruh galat dikembalikan menggunakan HTTP Status Code yang tepat (kelompok `4xx` untuk kesalahan klien dan `5xx` untuk galat server) dengan _Header_ `Content-Type: application/problem+json`.

### 4.1. Galat `404 Not Found` (Produk/SKU Tidak Ditemukan)

Terjadi jika kasir melakukan scan barcode atau pencarian SKU yang tidak terdaftar di database inventori.

```
HTTP/1.1 404 Not Found
Content-Type: application/problem+json

{
  "type": "https://warungpakhendy.test/problem/tidak-ditemukan",
  "title": "Sumber Daya Tidak Ditemukan",
  "status": 404,
  "detail": "Produk dengan kode SKU 'BRG-999' tidak terdaftar pada database inventori.",
  "instance": "/api/v1/produk/BRG-999"
}
```

### 4.2. Galat `409 Conflict` (Stok Barang Tidak Cukup)

Terjadi jika transaksi POS meminta pemotongan stok melebihi jumlah sisa stok fisik yang tersedia di database.

```
HTTP/1.1 409 Conflict
Content-Type: application/problem+json

{
  "type": "https://warungpakhendy.test/problem/stok-tidak-cukup",
  "title": "Stok Barang Tidak Mencukupi",
  "status": 409,
  "detail": "Gagal memproses transaksi. Stok untuk 'BRG-002' sisa 1 unit, tidak cukup untuk memenuhi pembelian 3 unit.",
  "instance": "/api/v1/transaksi",
  "stok_tersedia": 1
}
```

### 4.3. Galat `422 Unprocessable Content` (Validasi Payload Gagal)

Terjadi jika format JSON atau parameter input dari POS tidak memenuhi aturan validasi skema.

```
HTTP/1.1 422 Unprocessable Content
Content-Type: application/problem+json

{
  "type": "https://warungpakhendy.test/problem/validasi-gagal",
  "title": "Isi Permintaan Tidak Valid",
  "status": 422,
  "detail": "Satu atau lebih field pada data transaksi tidak memenuhi aturan spesifikasi API.",
  "instance": "/api/v1/transaksi",
  "errors": {
    "nomor_transaksi": [
      "Field nomor_transaksi wajib diisi."
    ],
    "items.0.jumlah_beli": [
      "Jumlah pembelian minimal adalah 1 unit."
    ]
  }
}
```

## 🔄 5. Pengelolaan Versi API (Versioning Strategy)

1. **Aturan Semantic Versioning (`MAJOR.MINOR.PATCH`):**
    
    - **PATCH (1.0.0 → 1.0.1):** Perbaikan bug internal (contoh: perbaikan rumus kalkulasi kembalian) yang tidak mengubah struktur JSON.
        
    - **MINOR (1.0.0 → 1.1.0):** Penambahan field baru di respons JSON yang bersifat kompatibel (_backward-compatible_), seperti menambahkan atribut `tersedia` atau `kategori`.
        
    - **MAJOR (1.0.0 → 2.0.0):** Perubahan mendasar yang merusak antarmuka lama (_breaking changes_), seperti mengubah tipe data harga dari integer ke string, atau menghapus atribut wajib.
        
2. **Penerapan URI Path:**
    
    - Versi **MAJOR** selalu dituliskan di awal _path_ URL (contoh: `/v1/`, `/v2/`).
        
    - Jika versi `v2` dirilis di kemudian hari, backend akan menggunakan strategi **Emulate the Old Interface**, di mana rute `/v1/` tetap dipertahankan sampai seluruh perangkat POS kasir selesai diperbarui.