<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Agent;

class AgentsTableSeeder extends Seeder
{
    public function run()
    {
        $agents = [
            ['name' => 'Agent 1'],
            ['name' => 'Agent 2'],
            ['name' => 'Agent 3'],
            ['name' => 'Agent 4'],
            ['name' => 'Agent 5'],
            ['name' => 'Agent 6'],
            ['name' => 'Agent 7'],
            ['name' => 'Agent 8'],
            ['name' => 'Agent 9'],
            ['name' => 'Agent 10']
        ];

        foreach ($agents as $agent) {
            Agent::firstOrCreate($agent);
        }
    }
}
