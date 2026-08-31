<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\Warehouse::firstOrCreate(
            ['id' => 1],
            ['code' => 'DEFAULT_WH', 'name' => 'Default Warehouse']
        );

        \App\Models\Location::firstOrCreate(
            ['id' => 1],
            ['code' => 'LOC-01', 'description' => 'Main Location']
        );

        User::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'System Admin',
                'email' => 'admin@peroniks.com',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $this->call([
            DepartmentSeeder::class,
            UserSeeder::class,
            AdjustmentReasonMasterSeeder::class,
            WarehouseGovernanceSeeder::class,
            WarehouseFamilySeeder::class,
            ProductionWarehouseAccessSeeder::class,
        ]);
    }
}
