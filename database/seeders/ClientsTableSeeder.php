<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ClientsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = [];

        for ($i = 1; $i <= 10; $i++) {
            $clients[] = [
                'shipping_plan_id' => rand(1, 5), // 假设 shipping_plan_id 在 1 到 5 之间
                'name' => 'Client ' . $i,
                'created_at' => Carbon::now()->subDays(rand(1, 30)),
                'updated_at' => Carbon::now(),
                'deleted_at' => null,
            ];
        }

        DB::table('clients')->insert($clients);
    }
}
