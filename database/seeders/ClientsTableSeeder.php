<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClientsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = [];
        $companies = ['Company A', 'Company B', 'Company C', 'Company D', 'Company E'];

        foreach ($companies as $company) {
            $clients[] = [
                'shipping_plan_id' => rand(1, 2), // 假设 shipping_plan_id 在 1 到 2 之间
                'name' => $company,
                'created_at' => Carbon::now()->subDays(rand(1, 30)),
                'updated_at' => Carbon::now(),
                'deleted_at' => null,
            ];
        }

        DB::table('clients')->insert($clients);
    }
}
