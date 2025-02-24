<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ShippingRate;

class ShippingRateSeeder extends Seeder
{
    public function run()
    {
        ShippingRate::insert([
            // 正常方向运费
            ['origin' => 'KCH', 'destination' => 'KUL', 'minimum_price' => 20.00, 'minimum_weight' => 5, 'additional_price_per_kg' => 4.00],
            ['origin' => 'KCH', 'destination' => 'SBW', 'minimum_price' => 5.00, 'minimum_weight' => 1, 'additional_price_per_kg' => 1.40],
            ['origin' => 'KCH', 'destination' => 'BTU', 'minimum_price' => 5.00, 'minimum_weight' => 1, 'additional_price_per_kg' => 1.80],
            ['origin' => 'KCH', 'destination' => 'MYY', 'minimum_price' => 5.00, 'minimum_weight' => 1, 'additional_price_per_kg' => 1.90],
            ['origin' => 'KCH', 'destination' => 'BKI', 'minimum_price' => 20.00, 'minimum_weight' => 5, 'additional_price_per_kg' => 4.50]
        ]);
        
    }
}
