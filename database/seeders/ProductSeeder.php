<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $ngn = Currency::where('code', 'NGN')->firstOrFail();

        $products = [
            [
                'name' => 'Nike Air Max',
                'sku' => 'NIKE-AIR-MAX',
                'description' => 'Nike Air Max sneakers',
                'price' => 8500000,
            ],
            [
                'name' => 'Apple AirPods',
                'sku' => 'APPLE-AIRPODS',
                'description' => 'Apple AirPods wireless earbuds',
                'price' => 3500000,
            ],
            [
                'name' => 'Samsung Galaxy',
                'sku' => 'SAMSUNG-GALAXY',
                'description' => 'Samsung Galaxy smartphone',
                'price' => 12000000,
            ],
            [
                'name' => 'MacBook Air',
                'sku' => 'APPLE-MACBOOK-AIR',
                'description' => 'Apple MacBook Air',
                'price' => 18000000,
            ],
            [
                'name' => 'Logitech Mouse',
                'sku' => 'LOGITECH-MOUSE',
                'description' => 'Wireless Logitech mouse',
                'price' => 1500000,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                [
                    ...$product,
                    'currency_id' => $ngn->id,
                    'is_active' => true,
                ]
            );
        }
    }
}