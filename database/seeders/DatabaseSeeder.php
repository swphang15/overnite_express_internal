<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ShippingRate;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([


            SuperAdminSeeder::class,
            ShippingSeeder::class,
            ClientsTableSeeder::class,
            // ManifestSeeder::class,






        ]);
    }
}
