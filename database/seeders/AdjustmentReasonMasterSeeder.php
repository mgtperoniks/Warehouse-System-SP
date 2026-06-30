<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdjustmentReasonMaster;

class AdjustmentReasonMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reasons = [
            ['code' => 'SALAH_HITUNG', 'name' => 'Salah Hitung'],
            ['code' => 'BARANG_DITEMUKAN', 'name' => 'Barang Ditemukan'],
            ['code' => 'BARANG_HILANG', 'name' => 'Barang Hilang'],
            ['code' => 'PINDAH_RAK', 'name' => 'Pindah Rak'],
            ['code' => 'SALAH_SCAN', 'name' => 'Salah Scan'],
            ['code' => 'KERUSAKAN', 'name' => 'Kerusakan'],
            ['code' => 'KESALAHAN_SISTEM', 'name' => 'Kesalahan Sistem'],
            ['code' => 'LAINNYA', 'name' => 'Lainnya'],
        ];

        foreach ($reasons as $reason) {
            AdjustmentReasonMaster::updateOrCreate(
                ['code' => $reason['code']],
                ['name' => $reason['name'], 'is_active' => true]
            );
        }
    }
}
