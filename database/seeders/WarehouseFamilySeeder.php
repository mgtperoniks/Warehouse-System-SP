<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Warehouse;
use App\Models\WarehouseFamilyAssignment;

class WarehouseFamilySeeder extends Seeder
{
    public function run(): void
    {
        $assignments = [
            'SPAREPART' => ['5', '6', '7'],
            'RAW_MATERIAL' => ['1'],
            'CONSUMABLE' => ['2'],
        ];

        foreach ($assignments as $code => $families) {
            $warehouse = Warehouse::where('code', $code)->first();
            if ($warehouse) {
                foreach ($families as $family) {
                    WarehouseFamilyAssignment::firstOrCreate([
                        'warehouse_id' => $warehouse->id,
                        'family_code' => $family,
                    ]);
                }
            }
        }
    }
}
