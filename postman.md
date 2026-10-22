# Postman Collection for Toko Stok API

Base URL: `http://localhost:8000/api/v1`

## 1. Daftar Produk (GET /produk)

**Method:** `GET`  
**URL:** `{{baseUrl}}/produk`  
**Headers:**  
- `Accept: application/json`

**Response (Success):**  
- Status: `200 OK`  
- Body: Array of product objects  
```json
[
  {
    "id": 1,
    "sku": "TEST001",
    "name": "Produk Test",
    "stock": 12,
    "created_at": "2026-10-18T13:59:52.000000Z",
    "updated_at": "2026-10-18T14:00:09.000000Z"
  }
]
```

**Response (Error):**  
- Status: `500 Internal Server Error` (if server error)  
- Body: `{ "message": "Server error" }`

**cURL Example:**  
```bash
curl -X GET "http://localhost:8000/api/v1/produk" \
     -H "Accept: application/json"
```

---

## 2. Tambah Produk (POST /produk)

**Method:** `POST`  
**URL:** `{{baseUrl}}/produk`  
**Headers:**  
- `Content-Type: application/json`  
- `Accept: application/json`

**Request Body:**  
```json
{
  "sku": "TEST002",
  "name": "Produk Baru",
  "stock": 10
}
```

**Response (Success):**  
- Status: `201 Created`  
- Headers: `Location: http://localhost:8000/api/v1/produk/TEST002`  
- Body: The created product object  
```json
{
  "id": 2,
  "sku": "TEST002",
  "name": "Produk Baru",
  "stock": 10,
  "created_at": "2026-10-18T14:05:00.000000Z",
  "updated_at": "2026-10-18T14:05:00.000000Z"
}
```

**Response (Validation Error):**  
- Status: `422 Unprocessable Entity`  
- Body:  
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "sku": ["The sku field is required."],
    "name": ["The name field is required."],
    "stock": ["The stock field must be an integer."]
  }
}
```

**cURL Example:**  
```bash
curl -X POST "http://localhost:8000/api/v1/produk" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{"sku":"TEST002","name":"Produk Baru","stock":10}'
```

---

## 3. Detail Produk (GET /produk/{sku})

**Method:** `GET`  
**URL:** `{{baseUrl}}/produk/{sku}`  
**URL Parameters:**  
- `sku`: string (required) - e.g., `TEST001`

**Headers:**  
- `Accept: application/json`

**Response (Success):**  
- Status: `200 OK`  
- Body: Product object  
```json
{
  "id": 1,
  "sku": "TEST001",
  "name": "Produk Test",
  "stock": 12,
  "created_at": "2026-10-18T13:59:52.000000Z",
  "updated_at": "2026-10-18T14:00:09.000000Z"
}
```

**Response (Not Found):**  
- Status: `404 Not Found`  
- Body: Problem Details format  
```json
{
  "type": "about:blank",
  "title": "Product Not Found",
  "status": 404,
  "detail": "Product with SKU NONEXISTENT not found.",
  "instance": "http://localhost:8000/api/v1/produk/NONEXISTENT"
}
```

**cURL Example:**  
```bash
curl -X GET "http://localhost:8000/api/v1/produk/TEST001" \
     -H "Accept: application/json"
```

---

## 4. Sesuaikan Stok (POST /produk/{sku}/penyesuaian-stok)

**Method:** `POST`  
**URL:** `{{baseUrl}}/produk/{sku}/penyesuaian-stok`  
**URL Parameters:**  
- `sku`: string (required) - e.g., `TEST001`

**Headers:**  
- `Content-Type: application/json`  
- `Accept: application/json`

**Request Body:**  
```json
{
  "quantity": 5
}
```
*Note: quantity can be positive (increase stock) or negative (decrease stock).*

**Response (Success):**  
- Status: `200 OK`  
- Body: Updated product object  
```json
{
  "id": 1,
  "sku": "TEST001",
  "name": "Produk Test",
  "stock": 17,
  "created_at": "2026-10-18T13:59:52.000000Z",
  "updated_at": "2026-10-18T14:10:00.000000Z"
}
```

**Response (Not Found):**  
- Status: `404 Not Found`  
- Body: Problem Details format (same as above)

**Response (Conflict - Stock would go negative):**  
- Status: `409 Conflict`  
- Body: Problem Details format  
```json
{
  "type": "about:blank",
  "title": "Conflict",
  "status": 409,
  "detail": "Stock adjustment would result in negative stock.",
  "instance": "http://localhost:8000/api/v1/produk/TEST001/penyesuaian-stok"
}
```

**Response (Validation Error):**  
- Status: `422 Unprocessable Entity`  
- Body:  
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "quantity": ["The quantity field is required."]
  }
}
```

**cURL Example (Increase Stock):**  
```bash
curl -X POST "http://localhost:8000/api/v1/produk/TEST001/penyesuaian-stok" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{"quantity":5}'
```

**cURL Example (Decrease Stock):**  
```bash
curl -X POST "http://localhost:8000/api/v1/produk/TEST001/penyesuaian-stok" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{"quantity":-3}'
```

---

## Notes

- Pastikan server API berjalan dengan perintah: `php artisan serve --port=8000`  
- Jika menggunakan Docker dengan Laravel Sail, pastikan kontainer sedang berjalan dan akses via `http://localhost/api/v1` (jika menggunakan sail tanpa port mapping khusus).  
- Untuk menguji respons error, gunakan SKU yang tidak ada atau data yang tidak valid.

---  
*File ini dapat digunakan sebagai panduan manual untuk menguji API dengan Postman atau cURL.*  