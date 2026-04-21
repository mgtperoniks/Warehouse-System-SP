<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::create([
            'name' => 'Admin Sparepart',
            'email' => 'adminsp@peroniks.com',
            'password' => \Illuminate\Support\Facades\Hash::make('321password'),
            'role' => 'admin',
        ]);

        \App\Models\User::create([
            'name' => 'Manager PPIC',
            'email' => 'managerppic@peroniks.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role' => 'manager',
        ]);

        \App\Models\User::create([
            'name' => 'Auditor',
            'email' => 'auditor@peroniks.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'auditor',
        ]);
    }
}
