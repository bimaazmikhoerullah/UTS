# 📮 Panduan Pengetesan API dengan Postman (`postman.md`)

> **Proyek UTS:** Integrasi Sistem dan Komputasi Awan — Warung Kelontong Pak Hendy (Kelompok 67)  
> **Base URL:** `http://localhost:8090/api/v1`

---

## 📥 1. Impor Koleksi Postman
Berkas koleksi Postman yang siap impor tersedia di repositori pada jalur:  
📁 `docs/postman/xavyera-uts.postman_collection.json`

### Cara Impor:
1. Buka aplikasi **Postman**.
2. Klik tombol **Import** di pojok kiri atas.
3. Seret dan letakkan (drag & drop) berkas `xavyera-uts.postman_collection.json` ke dalam jendela Postman.
4. Koleksi **"Warung Pak Hendy - UTS API"** akan muncul di sidebar kiri Anda.

---

## ⚙️ 2. Pengaturan Variabel Environment Postman
Buat Environment baru di Postman dengan nama `Warung Lokal` dan tambahkan variabel berikut:
- `baseUrl`: `http://localhost:8090/api/v1`

---

## 🧪 3. Daftar Permintaan (*Requests*) dalam Koleksi & Contoh Pengujian

### A. Ambil Daftar Produk (Katalog)
- **Method:** `GET`
- **URL:** `{{baseUrl}}/produk`
- **Deskripsi:** Mengambil seluruh daftar produk beserta sisa stok fisik dan batas minimum reorder.
- **Headers:** `Accept: application/json`
- **Contoh Respons (`200 OK`):**
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

### B. Detail Produk Berdasarkan SKU
- **Method:** `GET`
- **URL:** `{{baseUrl}}/produk/MYK-001`
- **Deskripsi:** Mengambil detail satu produk spesifik.

---

### C. Transaksi Penjualan Berhasil (POS Checkout)
- **Method:** `POST`
- **URL:** `{{baseUrl}}/transaksi`
- **Headers:**
  - `Content-Type: application/json`
  - `Accept: application/json, application/problem+json`
- **Body (raw JSON):**
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
- **Contoh Respons Sukses (`201 Created`):**
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

---

### D. Transaksi Gagal — Stok Kurang (`409 Conflict`)
- **Method:** `POST`
- **URL:** `{{baseUrl}}/transaksi`
- **Headers:**
  - `Content-Type: application/json`
  - `Accept: application/problem+json`
- **Body (raw JSON):**
```json
{
  "items": [
    {
      "sku": "MYK-001",
      "quantity": 9999
    }
  ]
}
```
- **Contoh Respons Galat Berstandar RFC 9457 (`409 Conflict`):**
```json
{
  "type": "about:blank",
  "title": "Conflict",
  "status": 409,
  "detail": "STOK_TIDAK_CUKUP: MYK-001",
  "instance": "http://localhost:8090/api/v1/transaksi"
}
```

---

### E. Transaksi Gagal — Validasi Input (`422 Unprocessable Content`)
- **Method:** `POST`
- **URL:** `{{baseUrl}}/transaksi`
- **Body (raw JSON - kuantitas bernilai negatif):**
```json
{
  "items": [
    {
      "sku": "MYK-001",
      "quantity": -1
    }
  ]
}
```
- **Contoh Respons (`422 Unprocessable Content`):**
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

### F. Buat Pemesanan Ulang ke Supplier (Reorder)
- **Method:** `POST`
- **URL:** `{{baseUrl}}/persediaan/reorder`
- **Headers:** `Content-Type: application/json`
- **Body (raw JSON):**
```json
{
  "sku": "MYK-001",
  "quantity": 15,
  "supplier": "PT Sumber Jaya Grosir",
  "notes": "Restok mendesak"
}
```
- **Respons Sukses (`201 Created`):** Mengembalikan objek status pemesanan.

---

### G. Riwayat Pemesanan Ulang
- **Method:** `GET`
- **URL:** `{{baseUrl}}/persediaan/reorder`
- **Deskripsi:** Mengambil daftar riwayat restock ke supplier.
