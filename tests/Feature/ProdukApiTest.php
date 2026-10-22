<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProdukApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_list_products()
    {
        $response = $this->getJson('/api/v1/produk');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     '*' => [
                         'sku',
                         'name',
                         'stock'
                     ]
                 ]);
    }

    public function test_it_can_create_a_product()
    {
        $productData = [
            'sku' => 'TEST001',
            'name' => 'Produk Test',
            'harga' => 10000,
            'stock' => 10
        ];

        $response = $this->postJson('/api/v1/produk', $productData);

        $response->assertStatus(201)
                 ->assertHeader('Location')
                 ->assertJsonFragment($productData);
    }

    public function test_it_can_get_a_product_by_sku()
    {
        // First create a product
        $productData = [
            'sku' => 'TEST002',
            'name' => 'Produk Test 2',
            'harga' => 15000,
            'stock' => 5
        ];
        $createResponse = $this->postJson('/api/v1/produk', $productData);
        $createResponse->assertStatus(201);

        // Then get it by SKU
        $response = $this->getJson('/api/v1/produk/TEST002');

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'sku' => 'TEST002',
                     'name' => 'Produk Test 2',
                     'stock' => 5
                 ]);
    }

    public function test_it_returns_404_for_nonexistent_product()
    {
        $response = $this->getJson('/api/v1/produk/NONEXISTENT');

        $response->assertStatus(404);
    }

    public function test_it_can_adjust_stock()
    {
        // Create a product with initial stock
        $productData = [
            'sku' => 'TEST003',
            'name' => 'Produk Test 3',
            'harga' => 20000,
            'stock' => 10
        ];
        $this->postJson('/api/v1/produk', $productData)->assertStatus(201);

        // Increase stock by 5
        $adjustment = ['quantity' => 5];
        $response = $this->postJson('/api/v1/produk/TEST003/penyesuaian-stok', $adjustment);

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'sku' => 'TEST003',
                     'name' => 'Produk Test 3',
                     'stock' => 15
                 ]);

        // Decrease stock by 3
        $adjustment = ['quantity' => -3];
        $response = $this->postJson('/api/v1/produk/TEST003/penyesuaian-stok', $adjustment);

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'sku' => 'TEST003',
                     'name' => 'Produk Test 3',
                     'stock' => 12
                 ]);
    }

    public function test_it_returns_404_when_adjusting_stock_for_nonexistent_product()
    {
        $adjustment = ['quantity' => 5];
        $response = $this->postJson('/api/v1/produk/NONEXISTENT/penyesuaian-stok', $adjustment);

        $response->assertStatus(404);
    }

    public function test_it_returns_409_when_stock_would_go_negative()
    {
        // Create a product with stock 5
        $productData = [
            'sku' => 'TEST004',
            'name' => 'Produk Test 4',
            'harga' => 25000,
            'stock' => 5
        ];
        $this->postJson('/api/v1/produk', $productData)->assertStatus(201);

        // Try to reduce stock by 10 (would go negative)
        $adjustment = ['quantity' => -10];
        $response = $this->postJson('/api/v1/produk/TEST004/penyesuaian-stok', $adjustment);

        $response->assertStatus(409);
    }

    public function test_it_validates_input_when_creating_product()
    {
        // Missing required fields
        $response = $this->postJson('/api/v1/produk', []);
        $response->assertStatus(422);

        // Invalid stock (negative)
        $response = $this->postJson('/api/v1/produk', [
            'sku' => 'TEST005',
            'name' => 'Produk Test 5',
            'stock' => -1
        ]);
        $response->assertStatus(422);
    }

    public function test_it_validates_input_when_adjusting_stock()
    {
        // Create a product first
        $productData = [
            'sku' => 'TEST006',
            'name' => 'Produk Test 6',
            'harga' => 30000,
            'stock' => 10
        ];
        $this->postJson('/api/v1/produk', $productData)->assertStatus(201);

        // Missing quantity
        $response = $this->postJson('/api/v1/produk/TEST006/penyesuaian-stok', []);
        $response->assertStatus(422);

        // Quantity not an integer
        $response = $this->postJson('/api/v1/produk/TEST006/penyesuaian-stok', ['quantity' => 'abc']);
        $response->assertStatus(422);
    }

    public function test_api_v1_root_returns_metadata()
    {
        $response = $this->getJson('/api/v1');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'name',
                     'version',
                     'status',
                     'endpoints'
                 ]);
    }

    public function test_it_can_adjust_stock_via_direct_sku_endpoint()
    {
        $productData = [
            'sku' => 'TEST007',
            'name' => 'Produk Test 7',
            'harga' => 35000,
            'stock' => 20
        ];
        $this->postJson('/api/v1/produk', $productData)->assertStatus(201);

        $adjustment = ['quantity' => 10];
        $response = $this->postJson('/api/v1/produk/TEST007', $adjustment);

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'sku' => 'TEST007',
                     'name' => 'Produk Test 7',
                     'stock' => 30
                 ]);
    }
}