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
        \App\Models\User::firstOrCreate(
            ['email' => 'adminsp@peroniks.com'],
            [
                'name' => 'Admin Sparepart',
                'password' => \Illuminate\Support\Facades\Hash::make('321password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        \App\Models\User::firstOrCreate(
            ['email' => 'adminbahanbaku@peroniks.com'],
            [
                'name' => 'Admin Bahan Baku',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        \App\Models\User::firstOrCreate(
            ['email' => 'managerppic@peroniks.com'],
            [
                'name' => 'Manager PPIC',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'manager',
                'is_active' => true,
            ]
        );

        \App\Models\User::firstOrCreate(
            ['email' => 'auditor@peroniks.com'],
            [
                'name' => 'Auditor',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'auditor',
                'is_active' => true,
            ]
        );
    }
}
