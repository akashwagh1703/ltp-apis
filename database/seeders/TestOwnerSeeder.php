<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestOwnerSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('owners')->insert([
            'name' => 'Test Owner',
            'phone' => '1234567890',
            'email' => 'owner@test.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
