<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Default Admin User
        User::firstOrCreate(
            ['email' => 'admin@warungpakhendy.test'],
            [
                'name' => 'Pak Hendy',
                'password' => bcrypt('password'),
            ]
        );

        // Produk Awal Warung Pak Hendy
        $products = [
            [
                'sku' => 'MYK-001',
                'name' => 'Minyak Goreng Bimoli 1L',
                'harga' => 20000,
                'stock' => 25,
                'minimum_stock' => 5,
            ],
            [
                'sku' => 'BRS-001',
                'name' => 'Beras Pandan Wangi 5kg',
                'harga' => 75000,
                'stock' => 15,
                'minimum_stock' => 3,
            ],
            [
                'sku' => 'GLA-001',
                'name' => 'Gula Pasir Gulaku 1kg',
                'harga' => 18000,
                'stock' => 30,
                'minimum_stock' => 5,
            ],
            [
                'sku' => 'MIE-001',
                'name' => 'Indomie Goreng Spesial',
                'harga' => 3500,
                'stock' => 100,
                'minimum_stock' => 20,
            ],
            [
                'sku' => 'TLR-001',
                'name' => 'Telur Ayam Negeri 1kg',
                'harga' => 28000,
                'stock' => 20,
                'minimum_stock' => 5,
            ],
            [
                'sku' => 'TPO-001',
                'name' => 'Tepung Terigu Segitiga Biru 1kg',
                'harga' => 14000,
                'stock' => 18,
                'minimum_stock' => 4,
            ],
            [
                'sku' => 'KCP-001',
                'name' => 'Kecap Manis Bango 520ml',
                'harga' => 22000,
                'stock' => 12,
                'minimum_stock' => 3,
            ],
        ];

        foreach ($products as $item) {
            \App\Models\Product::updateOrCreate(
                ['sku' => $item['sku']],
                $item
            );
        }
    }
}
