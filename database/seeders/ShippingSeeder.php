<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShippingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 禁用外键约束
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 清空表数据
        DB::table('shipping_rates')->truncate();
        DB::table('shipping_plans')->truncate();

        // 启用外键约束
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 生成 2 个 Shipping Plans
        $plans = [
            [
                'id' => 1,
                'plan_name' => 'Plan 1',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 2,
                'plan_name' => 'Plan 2',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];
        DB::table('shipping_plans')->insert($plans);

        // 生成 Shipping Rates（两个 Plan 使用相同的运费规则）
        $rates = [];
        $shipping_data = [
            ['origin' => 'KCH', 'destination' => 'KUL', 'minimum_price' => 20.00, 'minimum_weight' => 5, 'additional_price_per_kg' => 4.00],
            ['origin' => 'KCH', 'destination' => 'SBW', 'minimum_price' => 5.00, 'minimum_weight' => 1, 'additional_price_per_kg' => 1.40],
            ['origin' => 'KCH', 'destination' => 'BTU', 'minimum_price' => 5.00, 'minimum_weight' => 1, 'additional_price_per_kg' => 1.80],
            ['origin' => 'KCH', 'destination' => 'MYY', 'minimum_price' => 5.00, 'minimum_weight' => 1, 'additional_price_per_kg' => 1.90],
            ['origin' => 'KCH', 'destination' => 'BKI', 'minimum_price' => 20.00, 'minimum_weight' => 5, 'additional_price_per_kg' => 4.50],
        ];

        foreach ($plans as $plan) {
            foreach ($shipping_data as $data) {
                $rates[] = [
                    'shipping_plan_id' => $plan['id'],
                    'origin' => $data['origin'],
                    'destination' => $data['destination'],
                    'minimum_price' => $data['minimum_price'],
                    'minimum_weight' => $data['minimum_weight'],
                    'additional_price_per_kg' => $data['additional_price_per_kg'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        DB::table('shipping_rates')->insert($rates);
    }
}
