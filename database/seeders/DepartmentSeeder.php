<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $depts = [
            ['name' => 'Maintenance', 'code' => 'MAINT'],
            ['name' => 'Production', 'code' => 'PROD'],
            ['name' => 'Engineering', 'code' => 'ENG'],
            ['name' => 'General Affair', 'code' => 'GA'],
        ];

        foreach ($depts as $dept) {
            \App\Models\Department::firstOrCreate(['code' => $dept['code']], $dept);
        }

        // Link existing users to the first department for testing
        $defaultDept = \App\Models\Department::where('code', 'MAINT')->first();
        if ($defaultDept) {
            \App\Models\User::whereNull('department_id')->update(['department_id' => $defaultDept->id]);
        }
    }
}
