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
        // Removed truncate() to avoid mutating existing operational tables in production/staging.

        $reasons = [
            // Group 1 — Human Error
            ['code' => 'COUNTING_ERROR', 'name' => 'Salah Hitung'],
            ['code' => 'WRONG_SCAN', 'name' => 'Salah Scan Barcode'],
            ['code' => 'WRONG_BIN', 'name' => 'Salah Rak / Salah Penempatan'],
            ['code' => 'WRONG_PICK', 'name' => 'Salah Ambil Barang'],

            // Group 2 — Found Items
            ['code' => 'FOUND_ITEM', 'name' => 'Barang Ditemukan'],
            ['code' => 'RETURN_FOUND', 'name' => 'Barang Retur Ditemukan'],
            ['code' => 'LEFTOVER_FOUND', 'name' => 'Sisa Produksi Ditemukan'],

            // Group 3 — Missing / Damaged
            ['code' => 'MISSING_ITEM', 'name' => 'Barang Tidak Ditemukan'],
            ['code' => 'DAMAGED_ITEM', 'name' => 'Barang Rusak'],
            ['code' => 'EXPIRED_ITEM', 'name' => 'Barang Kadaluarsa / Tidak Layak Pakai'],

            // Group 4 — Movement Errors
            ['code' => 'MOVED_WITHOUT_SCAN', 'name' => 'Dipindahkan Tanpa Scan'],
            ['code' => 'TRANSFER_ERROR', 'name' => 'Salah Transfer Gudang'],

            // Group 5 — System
            ['code' => 'ERP_ERROR', 'name' => 'Kesalahan ERP'],
            ['code' => 'SYSTEM_ERROR', 'name' => 'Kesalahan Sistem WMS'],

            // Group 6 — Other (Preserving stable reason code 'LAINNYA')
            ['code' => 'LAINNYA', 'name' => 'Lainnya'],
        ];

        foreach ($reasons as $reason) {
            AdjustmentReasonMaster::updateOrCreate(
                ['code' => $reason['code']],
                [
                    'name' => $reason['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}

