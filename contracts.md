# 📜 API & Messaging Contracts Specification — Warung Pak Hendy

Dokumen ini mendokumentasikan spesifikasi teknis aturan kontrak data, aturan skema (_schema rules_), strategi pengelolaan versi (_versioning strategy_), serta pencegahan kegagalan integrasi (_structural & semantic breakage_) untuk **Sistem Manajemen Operasional & Kasir Digital Warung Pak Hendy** (Kelompok 67).

## 🛡️ 1. Prinsip Desain Kontrak & Mitigasi Kerusakan (Breakage)

Integrasi antarkomponen di Warung Pak Hendy menerapkan prinsip **Design-First** untuk mencegah dua jenis kerusakan integrasi sistem (Newman, 2021):

1. **Structural Breakage (Kerusakan Bentuk/Struktur):**
    
    - _Risiko:_ Perubahan nama field, penghapusan field, atau penambahan field wajib baru pada payload yang membuat sistem penerima crash.
        
    - _Pencegahan:_
        
        - Penerapan **Tolerant Reader Pattern**: Layanan penerima (POS / Supplier Order Service) hanya membaca field yang dibutuhkan dan mengabaikan (_ignore_) field tambahan baru yang belum dikenali.
            
        - **Expansion Changes**: Perubahan data dilakukan dengan menambah atribut baru, bukan menghapus atau mengubah atribut yang sudah ada.
            
2. **Semantic Breakage (Kerusakan Makna):**
    
    - _Risiko:_ Respons API tetap berformat JSON rapi tetapi mengandung makna status yang menyesatkan (misal: stok habis namun membalas dengan status HTTP `200 OK` dan body `{"status": "gagal"}`).
        
    - _Pencegahan:_
        
        - Wajib mematuhi **Richardson Maturity Model Level 2** dengan pemanfaatan HTTP Status Code secara tepat dan jujur.
            
        - Penerapan standar format galat **RFC 9457 Problem Details** (`application/problem+json`).
            

## 📐 2. Skema Kontrak Synchronous (REST API Payload)

### A. Request Payload: Transaksi Penjualan (`POST /v1/transaksi`)

```
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "title": "TransaksiPenjualanRequest",
  "type": "object",
  "required": ["nomor_transaksi", "kasir_id", "metode_pembayaran", "items", "total_bayar"],
  "properties": {
    "nomor_transaksi": {
      "type": "string",
      "pattern": "^TRX-[0-9]{8}-[0-9]{3}$",
      "description": "Format: TRX-YYYYMMDD-XXX"
    },
    "kasir_id": {
      "type": "integer",
      "minimum": 1
    },
    "metode_pembayaran": {
      "type": "string",
      "enum": ["tunai", "qris", "debit"]
    },
    "items": {
      "type": "array",
      "minItems": 1,
      "items": {
        "type": "object",
        "required": ["sku", "jumlah_beli", "harga_satuan"],
        "properties": {
          "sku": {
            "type": "string",
            "pattern": "^BRG-[0-9]{3}$"
          },
          "jumlah_beli": {
            "type": "integer",
            "minimum": 1
          },
          "harga_satuan": {
            "type": "integer",
            "minimum": 0
          }
        }
      }
    },
    "subtotal": { "type": "integer", "minimum": 0 },
    "total_bayar": { "type": "integer", "minimum": 0 },
    "kembalian": { "type": "integer", "minimum": 0 }
  }
}
```

### B. Standard Error Schema (RFC 9457 Problem Details)

```
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "title": "ProblemDetails",
  "type": "object",
  "required": ["type", "title", "status", "detail", "instance"],
  "properties": {
    "type": { "type": "string", "format": "uri" },
    "title": { "type": "string" },
    "status": { "type": "integer" },
    "detail": { "type": "string" },
    "instance": { "type": "string" },
    "errors": { "type": "object" }
  }
}
```

## 📨 3. Skema Kontrak Asynchronous (Event Message Payload)

### Event Name: `StockThresholdReached`

- **Broker Exchange:** `inventory.events` (Topic)
    
- **Routing Key:** `stock.threshold.reached`
    

```
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "title": "StockThresholdReachedEvent",
  "type": "object",
  "required": ["event_id", "nama_event", "waktu_kejadian", "data"],
  "properties": {
    "event_id": {
      "type": "string",
      "format": "uuid",
      "description": "Pengenal unik event untuk mekanisme Idempotent Consumer"
    },
    "nama_event": {
      "type": "string",
      "const": "StockThresholdReached"
    },
    "waktu_kejadian": {
      "type": "string",
      "format": "date-time"
    },
    "data": {
      "type": "object",
      "required": ["sku", "nama_produk", "sisa_stok", "stok_minimum"],
      "properties": {
        "sku": { "type": "string", "pattern": "^BRG-[0-9]{3}$" },
        "nama_produk": { "type": "string" },
        "sisa_stok": { "type": "integer" },
        "stok_minimum": { "type": "integer" },
        "rekomendasi_jumlah_restock": { "type": "integer" }
      }
    }
  }
}
```

## 🔄 4. Aturan Versioning & Pengelolaan Perubahan Kontrak

Sistem menggunakan standar **Semantic Versioning (`MAJOR.MINOR.PATCH`)** untuk mengelola evolusi kontrak:

```
       v1.0.0 (Rilis Awal)
         │
         ├──► PATCH (v1.0.1): Perbaikan bug logika internal backend (Tidak mengubah struktur JSON/API)
         │
         ├──► MINOR (v1.1.0): Penambahan field opsional (Misal: 'kategori' di katalog atau 'rekomendasi_jumlah_restock' di event)
         │
         └──► MAJOR (v2.0.0): Breaking Changes! (Misal: Perubahan format SKU dari 'BRG-001' menjadi UUID)
```

### Strategi Transisi Breaking Change (MAJOR Version)

Jika terdapat perubahan struktur yang merusak kompatibilitas lama (_breaking change_):

1. **URI Path Prefixing:** Versi baru diakses melalui prefix rute baru, misalnya `/api/v2/transaksi`.
    
2. **Interface Deprecation Notice:** Rute versi lama `/api/v1/transaksi` tetap dijalankan (_backward compatibility mode_) dengan menambahkan header `Deprecation: true` dan `Sunset: Wed, 11 Nov 2026 23:59:59 GMT`.
    
3. Kasir lama disinkronkan secara perlahan tanpa menyebabkan _downtime_ pada operasional warung.