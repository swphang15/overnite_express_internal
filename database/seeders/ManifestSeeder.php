<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ManifestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 禁用外键约束
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 清空表数据
        DB::table('manifest_infos')->truncate();
        DB::table('manifest_lists')->truncate();

        // 启用外键约束
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $year = Carbon::now()->year; // Get current year (e.g., 2025)
        $month = str_pad(3, 2, '0', STR_PAD_LEFT);

        $plans = [];
        for ($i = 1; $i <= 20; $i++) {
            $manifest_no = $year . $month . str_pad($i, 3, '0', STR_PAD_LEFT); // Format YYYYMM### (e.g., 202502001)

            $plans[] = [
                'user_id' => '1',
                'manifest_no' => $manifest_no, // YYYYMM###
                'date' => '2025-03-24',
                'awb_no' => 'I-' . rand(100, 9999) . '-' . rand(1, 99),
                'to' => ['KUL', 'SBW', 'BKI', 'JHB'][array_rand(['KUL', 'SBW', 'BKI', 'JHB'])],
                'from' => 'KCH',
                'flt' => 'COD',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        // Add 2 records with user_id 2
        for ($i = 21; $i <= 22; $i++) {
            $manifest_no = $year . $month . str_pad($i, 3, '0', STR_PAD_LEFT); // YYYYMM###

            $plans[] = [
                'user_id' => '2',
                'manifest_no' => $manifest_no, // YYYYMM###
                'date' => '2025-03-24',
                'awb_no' => 'I-' . rand(100, 9999) . '-' . rand(1, 99),
                'to' => ['KUL', 'SBW', 'BKI', 'JHB'][array_rand(['KUL', 'SBW', 'BKI', 'JHB'])],
                'from' => 'KCH',
                'flt' => 'COD',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        // Insert manifests and retrieve their IDs
        DB::table('manifest_infos')->insert($plans);
        $manifestRecords = DB::table('manifest_infos')->get(); // Fetch all inserted records

        // 生成 Shipping Rates
        $rates = [];
        foreach ($manifestRecords as $plan) {
            for ($j = 1; $j <= 5; $j++) { // Generate 5 items per manifest
                $rates[] = [
                    'manifest_info_id' => $plan->id,
                    'consignor_id' => rand(1, 2),
                    'consignee_name' => 'Agent ' . rand(1, 5),
                    'cn_no' => rand(100, 10000), // CN number within 100 - 10000
                    'pcs' => rand(1, 50),
                    'kg' => rand(1, 50),
                    'gram' => rand(100, 900),
                    'total_price' => rand(50, 500),
                    'origin' => 'KCH',
                    'destination' => ['SBW', 'BKI', 'JHB'][array_rand(['SBW', 'BKI', 'JHB'])],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        DB::table('manifest_lists')->insert($rates);
    }
}
