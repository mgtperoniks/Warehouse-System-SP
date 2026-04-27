<?php

namespace App\Livewire\Items;

use Livewire\Component;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemBarcode;
use App\Models\Supplier;
use App\Models\Bin;
use App\Models\Location;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkImport extends Component
{
    public $importResults = null;
    public $isProcessing = false;

    public function saveItems($data)
    {
        $this->isProcessing = true;
        
        $successCount = 0;
        $rejectedCount = 0;
        $rejectedDetails = [];

        foreach ($data as $index => $row) {
            // Basic validation: Name, ERP Code, SKU, Unit are mandatory
            $name = trim($row[0] ?? '');
            $erpCode = trim($row[1] ?? '');
            $sku = trim($row[2] ?? '');
            $unit = trim($row[3] ?? 'PCS');

            if (empty($name) || empty($erpCode)) {
                continue; // Skip empty rows
            }

            DB::beginTransaction();
            try {
                // 1. Check for ERP Code Duplication
                $existing = ItemVariant::where('erp_code', $erpCode)->exists();
                if ($existing) {
                    $rejectedCount++;
                    $rejectedDetails[] = [
                        'row' => $index + 1,
                        'erp_code' => $erpCode,
                        'reason' => 'ERP Code already exists'
                    ];
                    DB::rollBack();
                    continue;
                }

                // 2. Create/Find parent Item
                $item = Item::firstOrCreate(['name' => $name]);

                // 3. Create Variant
                $variant = ItemVariant::create([
                    'item_id' => $item->id,
                    'erp_code' => $erpCode,
                    'sku' => $sku,
                    'unit' => $unit,
                    'brand' => trim($row[4] ?? ''),
                    'price' => (float) filter_var($row[8] ?? 0, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION),
                    'description' => trim($row[9] ?? ''),
                ]);

                // 4. Handle Supplier
                $supplierName = trim($row[5] ?? '');
                if (!empty($supplierName)) {
                    $supplier = Supplier::firstOrCreate(['name' => $supplierName]);
                    $variant->suppliers()->syncWithoutDetaching([$supplier->id]);
                }

                // 5. Handle Barcode
                $barcode = trim($row[10] ?? '');
                if (!empty($barcode)) {
                    ItemBarcode::create([
                        'item_variant_id' => $variant->id,
                        'barcode' => $barcode,
                        'type' => 'SUPPLIER',
                        'is_primary' => true
                    ]);
                }

                // 6. Handle Bin & Initial Stock
                $binCode = trim($row[6] ?? '');
                
                // Robust numeric parsing for stock (handling potential formatting from Excel/Handsontable)
                $stockRaw = $row[7] ?? 0;
                $initialStock = is_numeric($stockRaw) ? (int)$stockRaw : (int)filter_var($stockRaw, FILTER_SANITIZE_NUMBER_INT);

                // Process if there is a bin code OR if there is initial stock to be added
                if (!empty($binCode) || $initialStock > 0) {
                    $location = Location::firstOrCreate(
                        ['code' => 'MAIN'],
                        ['description' => 'Main Warehouse']
                    );

                    // If bin code is empty but we have stock, default to 'FRONT' or similar
                    $finalBinCode = !empty($binCode) ? strtoupper($binCode) : 'FRONT';

                    $bin = Bin::create([
                        'location_id' => $location->id,
                        'item_variant_id' => $variant->id,
                        'code' => $finalBinCode,
                        'current_qty' => 0
                    ]);

                    if ($initialStock > 0) {
                        try {
                            $inventoryService = app(InventoryService::class);
                            $inventoryService->moveStock(
                                $bin,
                                $initialStock,
                                'ADJUSTMENT',
                                'Initial Stock via Bulk Import',
                                (string)auth()->id()
                            );
                        } catch (\Exception $stockEx) {
                            Log::warning("Stock movement failed for {$erpCode}: " . $stockEx->getMessage());
                            // We don't necessarily want to roll back the whole item if just stock fails, 
                            // but in this case, the user wants the stock to be there.
                            throw $stockEx; 
                        }
                    }
                }

                DB::commit();
                $successCount++;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Bulk Import Error at Row " . ($index + 1) . ": " . $e->getMessage());
                $rejectedCount++;
                $rejectedDetails[] = [
                    'row' => $index + 1,
                    'erp_code' => $erpCode,
                    'reason' => 'System error: ' . $e->getMessage()
                ];
            }
        }

        $this->importResults = [
            'success' => $successCount,
            'rejected' => $rejectedCount,
            'details' => $rejectedDetails
        ];
        
        $this->isProcessing = false;
        
        $this->dispatch('importCompleted', $this->importResults);
    }

    public function render()
    {
        return view('livewire.items.bulk-import');
    }
}
