<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\User;

use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run()
    {
        $superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('admin1234'),
            'role' => 'superadmin',
        ]);
        
        $superadmin->tokens()->delete(); // 清除已有 token
        $token = $superadmin->createToken('superadmin-token')->plainTextToken;

        echo "Superadmin Token: " . $token . "\n";
    }
}
