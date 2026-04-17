<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemBarcode;
use App\Models\Supplier;

class ImportItemsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $filePath;
    public $userId;

    public function __construct($filePath, $userId = null)
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        $fullPath = storage_path('app/public/' . $this->filePath);
        if (!file_exists($fullPath)) {
            Log::error("ImportItemsJob Failed: File not found at {$fullPath}");
            return;
        }

        $extension = pathinfo($fullPath, PATHINFO_EXTENSION);
        $rows = [];
        
        // Simple CSV parser for robust bulk import
        if (strtolower($extension) === 'csv') {
            if (($handle = fopen($fullPath, "r")) !== FALSE) {
                $header = fgetcsv($handle, 1000, ",");
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($header) == count($data)) {
                        $rows[] = array_combine($header, $data);
                    }
                }
                fclose($handle);
            }
        } else {
            // Simplified fallback for testing without full excel dependencies loaded in memory
            Log::warning("Currently only CSV format is fully supported via this fast-path importer.");
            return;
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($rows as $row) {
            DB::beginTransaction();
            try {
                // Expected Headers: name, erp_code, sku, brand, unit, description, barcode, supplier_name
                $name = $row['name'] ?? null;
                $erpCode = $row['erp_code'] ?? null;
                $barcode = $row['barcode'] ?? null;

                if (!$name) {
                    throw new \Exception("Missing item name.");
                }

                $item = Item::firstOrCreate(['name' => trim($name)]);

                // Upsert Variant
                $variant = ItemVariant::where('erp_code', $erpCode)
                                      ->whereNotNull('erp_code')
                                      ->first();

                if (!$variant) {
                    $variant = new ItemVariant();
                    $variant->item_id = $item->id;
                }

                $variant->erp_code = $erpCode;
                $variant->sku = $row['sku'] ?? null;
                $variant->brand = $row['brand'] ?? null;
                $variant->unit = $row['unit'] ?? 'PCS';
                $variant->description = $row['description'] ?? null;
                $variant->save();

                // Assign primary barcode if provided
                if ($barcode) {
                    $existingBarcode = ItemBarcode::where('barcode', trim($barcode))->first();
                    if (!$existingBarcode) {
                        ItemBarcode::create([
                            'item_variant_id' => $variant->id,
                            'barcode' => trim($barcode),
                            'type' => 'SUPPLIER',
                            'is_primary' => true
                        ]);
                    }
                }

                // Attach Supplier if provided
                if (!empty($row['supplier_name'])) {
                    $supplier = Supplier::firstOrCreate(['name' => trim($row['supplier_name'])]);
                    $variant->suppliers()->syncWithoutDetaching([$supplier->id]);
                }

                DB::commit();
                $successCount++;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Failed importing row: " . json_encode($row) . " - " . $e->getMessage());
                $errorCount++;
            }
        }

        Log::info("Import completed. Success: {$successCount}, Errors: {$errorCount}");
        
        // Clean up file
        Storage::disk('public')->delete($this->filePath);
    }
}
