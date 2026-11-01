<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\OrderStatus;
use App\Models\SaleTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewRestApiFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $sku, int $stock, int $price = 1000, int $minimum = 5): Product
    {
        return Product::create([
            'sku' => $sku,
            'name' => ucfirst($sku),
            'harga' => $price,
            'stock' => $stock,
            'minimum_stock' => $minimum,
        ]);
    }

    public function test_it_records_a_sales_transaction_and_reduces_stock(): void
    {
        $this->product('TEST-SALE', stock: 10, price: 1500);

        $response = $this->postJson('/api/v1/transaksi', [
            'items' => [
                ['sku' => 'TEST-SALE', 'quantity' => 4],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertSame(6, Product::where('sku', 'TEST-SALE')->value('stock'));
        $this->assertDatabaseCount('sales_transactions', 1);
        $this->assertSame(6000, SaleTransaction::first()->total_price);
    }

    public function test_it_returns_conflict_when_transaction_quantity_exceeds_stock(): void
    {
        $this->product('TEST-SALE-CONFLICT', stock: 2, price: 1000);

        $response = $this->postJson('/api/v1/transaksi', [
            'items' => [
                ['sku' => 'TEST-SALE-CONFLICT', 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('sales_transactions', 0);
    }

    public function test_it_lists_transactions(): void
    {
        $this->product('TEST-LIST', stock: 5, price: 1000);
        $this->postJson('/api/v1/transaksi', ['items' => [['sku' => 'TEST-LIST', 'quantity' => 1]]])->assertStatus(201);

        $response = $this->getJson('/api/v1/transaksi');

        $response->assertStatus(200)->assertJsonStructure(['data' => ['*' => ['transaction_id', 'total_price', 'items']]]);
    }

    public function test_it_can_view_a_single_transaction(): void
    {
        $this->product('TEST-DETAIL', stock: 5, price: 2000);
        $created = $this->postJson('/api/v1/transaksi', ['items' => [['sku' => 'TEST-DETAIL', 'quantity' => 2]]]);
        $transactionId = $created->json('transaction.transaction_id');

        $response = $this->getJson("/api/v1/transaksi/{$transactionId}");

        $response->assertStatus(200)
            ->assertJsonFragment(['transaction_id' => $transactionId, 'total_price' => 4000]);
    }

    public function test_it_returns_404_for_unknown_transaction(): void
    {
        $response = $this->getJson('/api/v1/transaksi/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404)->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_stock_monitoring_reports_status_counts(): void
    {
        $this->product('MON-OK', stock: 50, minimum: 5);
        $this->product('MON-LOW', stock: 3, minimum: 5);
        $this->product('MON-OOS', stock: 0, minimum: 5);

        $response = $this->getJson('/api/v1/persediaan/monitor');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'total_products' => 3,
                'out_of_stock_count' => 1,
                'low_stock_count' => 1,
            ]);
    }

    public function test_it_creates_and_lists_reorders(): void
    {
        $this->product('REORDER-1', stock: 1);

        $create = $this->postJson('/api/v1/persediaan/reorder', [
            'sku' => 'REORDER-1',
            'quantity' => 25,
            'supplier' => 'PT Sumber Jaya',
            'notes' => 'reorder manual',
        ]);

        $create->assertStatus(201)->assertJsonFragment(['sku' => 'REORDER-1', 'quantity' => 25, 'status' => 'pending']);

        $list = $this->getJson('/api/v1/persediaan/reorder?status=pending&sku=REORDER-1');

        $list->assertStatus(200)
            ->assertJsonStructure(['data', 'total', 'filters']);
        $this->assertSame(1, OrderStatus::count());
    }

    public function test_reorder_creation_validates_existing_sku(): void
    {
        $response = $this->postJson('/api/v1/persediaan/reorder', [
            'sku' => 'NOT-EXIST',
            'quantity' => 10,
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, OrderStatus::count());
    }

    public function test_sales_report_returns_revenue_and_top_products(): void
    {
        $this->product('RPT-A', stock: 10, price: 2000);
        $this->product('RPT-B', stock: 10, price: 1000);

        $this->postJson('/api/v1/transaksi', [
            'items' => [
                ['sku' => 'RPT-A', 'quantity' => 3],
                ['sku' => 'RPT-B', 'quantity' => 2],
            ],
        ])->assertStatus(201);

        $response = $this->getJson('/api/v1/reports/sales');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'total_revenue' => 8000,
                'total_transactions' => 1,
            ])
            ->assertJsonStructure(['report', 'generated_at', 'total_revenue', 'total_transactions', 'top_products']);
    }

    public function test_stock_report_returns_inventory_metrics(): void
    {
        $this->product('RPT-S', stock: 10, price: 500, minimum: 5);
        $this->product('RPT-S2', stock: 0, price: 300, minimum: 4);

        $response = $this->getJson('/api/v1/reports/stock');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'total_products' => 2,
                'total_units' => 10,
                'stock_value' => 5000,
                'out_of_stock_count' => 1,
            ])
            ->assertJsonStructure(['report', 'generated_at', 'total_products', 'total_units', 'stock_value', 'low_stock_count', 'out_of_stock_count']);
    }
}
