<?php

namespace App\Services\Barcode;

use App\Models\InternalBarcodeCounter;
use Illuminate\Support\Facades\DB;

class BarcodeService
{
    /**
     * Generate a unique, incremental internal barcode.
     * Concurrency-safe using DB locks.
     */
    public function generateInternalBarcode(string $prefix = 'INT', int $digits = 9): string
    {
        return DB::transaction(function () use ($prefix, $digits) {
            // Get the counter and lock the row for update
            $counter = InternalBarcodeCounter::where('prefix', $prefix)
                ->lockForUpdate()
                ->first();

            if (!$counter) {
                $counter = InternalBarcodeCounter::create([
                    'prefix' => $prefix,
                    'current_value' => 0,
                ]);
            }

            $newValue = $counter->current_value + 1;
            $counter->update(['current_value' => $newValue]);

            // Format: INT-000000001
            return $prefix . '-' . str_pad($newValue, $digits, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Helper to generate and optionally link barcode to variant
     */
    public function generateInternalBarcodeForVariant(int $variantId): string
    {
        $barcode = $this->generateInternalBarcode();
        
        \App\Models\ItemBarcode::create([
            'item_variant_id' => $variantId,
            'barcode' => $barcode,
            'type' => 'INTERNAL',
            'is_primary' => true,
        ]);

        return $barcode;
    }

    /**
     * Normalize barcode string (trim, remove hidden characters).
     * 
     * @param string $barcode
     * @return string
     */
    public function normalize(string $barcode): string
    {
        // Simple trim and remove non-printable characters
        return trim(preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $barcode));
    }
}
