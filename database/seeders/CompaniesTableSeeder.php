<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompaniesTableSeeder extends Seeder
{
    public function run()
    {
        $companies = [
            ['name' => 'Company A'],
            ['name' => 'Company B'],
            ['name' => 'Company C'],
            ['name' => 'Company D'],
            ['name' => 'Company E'],
            ['name' => 'Company F'],
            ['name' => 'Company G'],
            ['name' => 'Company H'],
            ['name' => 'Company I'],
            ['name' => 'Company J']
        ];

        foreach ($companies as $company) {
            Company::firstOrCreate($company);
        }
    }
}
