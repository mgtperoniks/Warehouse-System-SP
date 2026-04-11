<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemBarcode;
use App\Services\Barcode\BarcodeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ItemImport implements ToCollection, WithHeadingRow
{
    protected $barcodeService;

    public function __construct()
    {
        $this->barcodeService = new BarcodeService();
    }

    /**
    * @param Collection $collection
    */
    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $erpCode = trim($row['erp_code'] ?? '');
                $productName = trim($row['product_name'] ?? '');
                $barcode = $this->barcodeService->normalize($row['barcode'] ?? '');
                
                if (empty($erpCode) || empty($productName)) {
                    Log::warning("Import skipped: Missing ERP Code or Product Name", $row->toArray());
                    continue;
                }

                // 1. Find or Create Item
                $item = Item::firstOrCreate(['name' => $productName]);

                // 2. Upsert ItemVariant on erp_code
                $variant = ItemVariant::updateOrCreate(
                    ['erp_code' => $erpCode],
                    [
                        'item_id' => $item->id,
                        'sku' => $row['sku'] ?? $erpCode,
                        'unit' => $row['unit'] ?? 'PCS',
                        'description' => $row['description'] ?? null,
                        'brand' => $row['brand'] ?? null,
                    ]
                );

                // 3. Handle Barcode
                if (!empty($barcode)) {
                    // Check if barcode exists globally
                    $existingBarcode = ItemBarcode::where('barcode', $barcode)->first();

                    if ($existingBarcode) {
                        if ($existingBarcode->item_variant_id !== $variant->id) {
                            Log::warning("Barcode collision: Barcode {$barcode} already belongs to ERP Code {$existingBarcode->variant->erp_code}. Skipped for ERP Code {$erpCode}.");
                        }
                        // If it belongs to the same variant, we don't need to do anything
                    } else {
                        // Create new supplier barcode
                        ItemBarcode::create([
                            'item_variant_id' => $variant->id,
                            'barcode' => $barcode,
                            'type' => 'SUPPLIER',
                            'is_primary' => true, // Latest imported supplier barcode becomes primary
                        ]);
                    }
                }

                // 4. Auto-generate internal barcode if none exists for this variant
                if ($variant->barcodes()->count() === 0) {
                    $internalBarcode = $this->barcodeService->generateInternalBarcode();
                    ItemBarcode::create([
                        'item_variant_id' => $variant->id,
                        'barcode' => $internalBarcode,
                        'type' => 'INTERNAL',
                        'is_primary' => true,
                    ]);
                }
            }
        });
    }
}
