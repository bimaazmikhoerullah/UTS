# 🧪 Integration Test Scenarios — Warung Pak Hendy

Dokumen ini mendokumentasikan skenario pengujian integrasi (_Integration Testing_), pengujian otomatis (_TDD / Feature Tests_), serta pengujian manual (_cURL Commands_) untuk **Sistem Manajemen Operasional & Kasir Digital Warung Pak Hendy** (Kelompok 67).

## 🎯 1. Matriks Skenario Pengujian System

|   |   |   |   |   |
|---|---|---|---|---|
|**ID Skenario**|**Layanan Terkait**|**Jenis Pengujian**|**Deskripsi Skenario**|**Ekspektasi Hasil**|
|**SCN-API-01**|POS $\rightarrow$ Backend|REST API|Memuat daftar produk & harga dari endpoint `/v1/produk`.|Return `200 OK`, JSON array produk valid.|
|**SCN-API-02**|POS $\rightarrow$ Backend|REST API|Transaksi penjualan dengan stok mencukupi (`POST /v1/transaksi`).|Return `201 Created`, stok terpotong di PostgreSQL.|
|**SCN-API-03**|POS $\rightarrow$ Backend|REST API|Transaksi penjualan dengan jumlah barang melebihi sisa stok fisik.|Return `409 Conflict`, RFC 9457 Problem Details, DB rollback.|
|**SCN-API-04**|POS $\rightarrow$ Backend|REST API|Transaksi dengan payload JSON tidak valid (SKU salah / qty 0).|Return `422 Unprocessable`, RFC 9457 validation errors.|
|**SCN-MSG-01**|Backend $\rightarrow$ RabbitMQ|Async Event|Transaksi berhasil dan sisa stok $\le$ stok minimum.|Event `StockThresholdReached` terbit ke `inventory.events`.|
|**SCN-MSG-02**|RabbitMQ $\rightarrow$ Supplier|Async Consumer|Consumer membaca event `StockThresholdReached` pertama kali.|Data restock tercatat, Consumer mengembalikan sinyal `ACK`.|
|**SCN-MSG-03**|RabbitMQ $\rightarrow$ Supplier|Idempotency|Consumer menerima event duplikat dengan `event_id` yang sama.|Event diabaikan, tidak ada order ganda, sinyal `ACK` dikirim.|
|**SCN-MSG-04**|RabbitMQ $\rightarrow$ DLQ|Dead Lettering|Payload event dalam queue rusak / gagal diolah melebihi batas retry.|Pesan di-`NACK(requeue=false)` dan dipindah ke `supplier.orders.dlq`.|

## 💻 2. Pengujian Manual via cURL Command

### A. Skenario SCN-API-01: Memuat Katalog Produk

```
curl -X GET "http://localhost/api/v1/produk" \
  -H "Accept: application/json"
```

### B. Skenario SCN-API-02: Transaksi Penjualan Sukses

```
curl -X POST "http://localhost/api/v1/transaksi" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "nomor_transaksi": "TRX-20261101-001",
    "kasir_id": 1,
    "metode_pembayaran": "tunai",
    "items": [
      {
        "sku": "BRG-001",
        "jumlah_beli": 2,
        "harga_satuan": 19500
      }
    ],
    "subtotal": 39000,
    "total_bayar": 40000,
    "kembalian": 1000
  }'
```

### C. Skenario SCN-API-03: Transaksi Ditolak (Stok Kurang / 409 Conflict)

```
curl -X POST "http://localhost/api/v1/transaksi" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "nomor_transaksi": "TRX-20261101-002",
    "kasir_id": 1,
    "metode_pembayaran": "tunai",
    "items": [
      {
        "sku": "BRG-002",
        "jumlah_beli": 100,
        "harga_satuan": 17500
      }
    ],
    "subtotal": 1750000,
    "total_bayar": 1750000,
    "kembalian": 0
  }'
```

## 🧪 3. Contoh Implementasi Laravel Feature Test (TDD)

### A. Feature Test: Penjualan & Validasi RFC 9457 (`TransaksiApiTest.php`)

```
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransaksiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaksi_berhasil_dan_memotong_stok(): void
    {
        $produk = Produk::factory()->create([
            'sku' => 'BRG-001',
            'stok' => 20,
            'harga' => 19500
        ]);

        $payload = [
            'nomor_transaksi' => 'TRX-20261101-088',
            'kasir_id' => 1,
            'metode_pembayaran' => 'tunai',
            'items' => [
                ['sku' => 'BRG-001', 'jumlah_beli' => 5, 'harga_satuan' => 19500]
            ],
            'subtotal' => 97500,
            'total_bayar' => 100000,
            'kembalian' => 2500
        ];

        $response = $this->postJson('/api/v1/transaksi', $payload);

        $response->assertStatus(201)
                 ->assertHeader('Location')
                 ->assertJsonPath('status', 'sukses');

        $this->assertDatabaseHas('produks', [
            'sku' => 'BRG-001',
            'stok' => 15
        ]);
    }

    public function test_transaksi_ditolak_jika_stok_kurang_format_problem_details(): void
    {
        $produk = Produk::factory()->create([
            'sku' => 'BRG-002',
            'stok' => 2,
            'harga' => 17500
        ]);

        $payload = [
            'nomor_transaksi' => 'TRX-20261101-089',
            'kasir_id' => 1,
            'metode_pembayaran' => 'tunai',
            'items' => [
                ['sku' => 'BRG-002', 'jumlah_beli' => 10, 'harga_satuan' => 17500]
            ],
            'subtotal' => 175000,
            'total_bayar' => 175000,
            'kembalian' => 0
        ];

        $response = $this->postJson('/api/v1/transaksi', $payload);

        $response->assertStatus(409)
                 ->assertHeader('Content-Type', 'application/problem+json')
                 ->assertJsonPath('title', 'Stok Barang Tidak Mencukupi');
    }
}
```

### B. Messaging Test: Pemicuan Event RabbitMQ (`StockEventPublishTest.php`)

```
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Produk;
use App\Messaging\RabbitPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

class StockEventPublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_memicu_event_rabbitmq_saat_stok_menyentuh_batas_minimum(): void
    {
        // Mocking Publisher agar tidak melakukan socket connection aktual saat test
        $this->mock(RabbitPublisher::class, function (MockInterface $mock) {
            $mock->shouldReceive('publishEvent')
                 ->once()
                 ->with('inventory.events', 'stock.threshold.reached', Mockery::type('array'));
        });

        $produk = Produk::factory()->create([
            'sku' => 'BRG-005',
            'stok' => 6,
            'stok_minimum' => 5
        ]);

        // Pembelian 2 unit menyebabkan sisa stok menjadi 4 (<= stok minimum 5)
        $payload = [
            'nomor_transaksi' => 'TRX-20261101-099',
            'kasir_id' => 1,
            'metode_pembayaran' => 'tunai',
            'items' => [
                ['sku' => 'BRG-005', 'jumlah_beli' => 2, 'harga_satuan' => 10000]
            ],
            'subtotal' => 20000,
            'total_bayar' => 20000,
            'kembalian' => 0
        ];

        $this->postJson('/api/v1/transaksi', $payload)->assertStatus(201);
    }
}
```