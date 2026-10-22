# Kontrak Event Asinkron

Jalur asinkron proyek ini memakai **event** yang dipublikasikan lewat message broker **RabbitMQ** (topologi *topic*). Sumber kode event: `app/Events/StokDisesuaikan.php` dan publisher-nya di `app/Messaging/RabbitPublisher.php`.

## Event: `StokDisesuaikan`

- **Bentuk nama:** lampau (menyatakan fakta yang sudah terjadi) — *sudah* disesuaikan.
- **Penerbit (publisher):** Layanan Stok & Katalog (`ProdukController@adjustStock`, lewat `Outbox::catat()` dalam satu transaksi DB bersama mutasi stok — *outbox pattern*).
- **Penerima (consumer):** Layanan Pembukuan (disimulasikan untuk checkpoint ini — cukup memverifikasi pesan masuk di RabbitMQ Management UI, atau consumer sederhana).

## Exchange & Routing Key

- **Exchange:** `toko.events`
  - Topology: `topic`
  - Durable: `true`
- **Routing key:** `stok.{alasan}` — `alasan` diambil dari payload (mis. `stok.penjualan` saat stok berkurang karena dijual, `stok.restok` saat stok ditambah).

## Field Payload (body pesan)

| Field | Tipe | Keterangan |
|---|---|---|
| `sku` | `string` | SKU produk yang stoknya berubah |
| `perubahan` | `integer` | Selisih stok (negatif = berkurang, positif = bertambah) |
| `alasan` | `string` | Penyebab perubahan, dipakai menyusun routing key |
| `stok_sesudah` | `integer` | Stok *sebelum* perubahan ini diterapkan |
| `harga` | `integer` | Harga satuan produk (untuk kebutuhan pembukuan) |
| `terjadi_pada` | `string` (ISO 8601) | Waktu kejadian |

Contoh payload:

```json
{
  "sku": "KRD-001",
  "perubahan": -3,
  "alasan": "penjualan",
  "stok_sesudah": 7,
  "harga": 45000,
  "terjadi_pada": "2026-10-22T12:34:56+07:00"
}
```

## Cara melihat event terbit/diterima

1. Pastikan RabbitMQ berjalan: `./rabit.sh on`.
2. Buka RabbitMQ Management UI: `http://localhost:15672` (user `xavyera`, pass `bima`).
3. Lakukan penyesuaian stok lewat REST (`POST /api/v1/produk/{sku}/penyesuaian-stok`).
4. Di UI, cek queue/exchange `toko.events` — pesan dengan routing key `stok.{alasan}` akan muncul di situ.
