<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShippingRatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('shipping_rates')->insert([
            [
                'origin' => 'KCH',
                'destination' => 'KUL',
                'minimum_price' => 20.00,
                'minimum_weight' => 5, // 5KG
                'additional_price_per_kg' => 4.00,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'origin' => 'KCH',
                'destination' => 'SBW',
                'minimum_price' => 5.00,
                'minimum_weight' => 1, // 1st KG
                'additional_price_per_kg' => 1.40,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'origin' => 'KCH',
                'destination' => 'BTU',
                'minimum_price' => 5.00,
                'minimum_weight' => 1, // 1st KG
                'additional_price_per_kg' => 1.80,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'origin' => 'KCH',
                'destination' => 'MYY',
                'minimum_price' => 5.00,
                'minimum_weight' => 1, // 1st KG
                'additional_price_per_kg' => 1.90,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'origin' => 'KCH',
                'destination' => 'BKI',
                'minimum_price' => 20.00,
                'minimum_weight' => 5, // 5KG
                'additional_price_per_kg' => 4.50,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}

