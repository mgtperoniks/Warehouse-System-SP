<?php

namespace App\Livewire\Opname;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Bin;
use App\Models\ItemBarcode;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\AdjustmentReasonMaster;

class OpnamePage extends Component
{
    public $binScan = '';
    public $isScanning = true;
    
    // Scanned State
    public $selectedBin = null;
    public $candidateBins = [];
    public $isSelectingBin = false;
    
    // Audit State
    public $actualQty = 0;
    public $systemQty = 0;
    public $difference = 0;

    // Variance Governance Fields
    public $reasonCode = '';
    public $notes = '';

    public function updatedBinScan($value)
    {
        $this->handleScan($value);
    }

    public function handleScan($value)
    {
        if (empty($value)) return;

        $this->resetAudit();

        // 1. Try to find Bin directly by code (Scoped to Active Warehouse)
        $bin = Bin::forActiveWarehouse()->where('code', $value)->first();
        if ($bin) {
            $this->loadBin($bin);
            return;
        }

        // 2. Try to find by Item Barcode (Scoped to Active Warehouse)
        $barcode = ItemBarcode::where('barcode', $value)->first();
        if ($barcode) {
            $variant = $barcode->variant;
            $bins = $variant->bins()->forActiveWarehouse()->get();

            if ($bins->count() === 0) {
                $this->addError('binScan', 'Item found but has no assigned bins in active warehouse.');
            } elseif ($bins->count() === 1) {
                $this->loadBin($bins->first());
            } else {
                $this->candidateBins = $bins;
                $this->isSelectingBin = true;
                $this->isScanning = false;
            }
            return;
        }

        $this->addError('binScan', 'No bin or item found with this code in active warehouse.');
    }

    public function selectBin($binId)
    {
        $bin = Bin::forActiveWarehouse()->find($binId);
        if ($bin) {
            $this->loadBin($bin);
        }
        $this->isSelectingBin = false;
    }

    public function loadBin($bin)
    {
        $this->selectedBin = $bin;
        $this->systemQty = $bin->current_qty;
        $this->actualQty = $bin->current_qty;
        $this->difference = 0;
        $this->isScanning = false;
        $this->binScan = ''; // Reset input for next time
    }

    public function resetAudit()
    {
        $this->selectedBin = null;
        $this->candidateBins = [];
        $this->isSelectingBin = false;
        $this->isScanning = true;
        $this->actualQty = 0;
        $this->systemQty = 0;
        $this->difference = 0;
        $this->reasonCode = '';
        $this->notes = '';
    }

    public function updatedActualQty($value)
    {
        $this->difference = (int)$value - $this->systemQty;
        // Reset reason/notes if variance goes back to 0
        if ($this->difference === 0) {
            $this->reasonCode = '';
            $this->notes = '';
        }
    }

    public function incrementQty()
    {
        $this->actualQty++;
        $this->updatedActualQty($this->actualQty);
    }

    public function decrementQty()
    {
        if ($this->actualQty > 0) {
            $this->actualQty--;
            $this->updatedActualQty($this->actualQty);
        }
    }

    public function saveItem()
    {
        if (!$this->selectedBin) return;

        $warehouseId = session()->get('active_warehouse_id');
        $operatorId = auth()->id();
        $today = date('Y-m-d');

        if (!$warehouseId) {
            session()->flash('error', 'No active warehouse selected.');
            return;
        }

        // Case A: Discrepancy detected (Variance !== 0) -> Governance Approval Route
        if ($this->difference !== 0) {
            // Validate inputs
            $rules = [
                'reasonCode' => 'required|string|exists:adjustment_reason_masters,code',
                'notes' => $this->reasonCode === 'LAINNYA' ? 'required|string|min:3' : 'nullable|string',
            ];
            
            $this->validate($rules);

            $header = null;
            try {
                // Atomic grouping find-or-create header
                $header = DB::transaction(function () use ($warehouseId, $operatorId, $today) {
                    $existing = InventoryAdjustment::where('warehouse_id', $warehouseId)
                        ->where('operator_id', $operatorId)
                        ->where('date', $today)
                        ->whereDoesntHave('basoDocument')
                        ->latest()
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        return $existing;
                    }

                    $adjustmentNo = InventoryAdjustment::generateCode($warehouseId, $operatorId, $today);
                    return InventoryAdjustment::create([
                        'adjustment_no' => $adjustmentNo,
                        'warehouse_id' => $warehouseId,
                        'operator_id' => $operatorId,
                        'date' => $today,
                        'status' => 'WAITING_APPROVAL',
                    ]);
                });
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle concurrent execution unique violations safely
                if ($e->getCode() === '23000') {
                    $header = InventoryAdjustment::where('warehouse_id', $warehouseId)
                        ->where('operator_id', $operatorId)
                        ->where('date', $today)
                        ->whereDoesntHave('basoDocument')
                        ->latest()
                        ->first();
                } else {
                    throw $e;
                }
            }

            if (!$header) {
                session()->flash('error', 'Failed to resolve inventory adjustment header.');
                return;
            }

            // Create Detail record with immutable snapshots inside transaction
            DB::transaction(function () use ($header) {
                $warehouseName = $header->warehouse->name;
                $operatorName = $header->operator->name;

                $variant = $this->selectedBin->itemVariant;
                $itemName = $variant->item->name;
                $erpCode = $variant->item->erp_code;
                $unit = $variant->item->unit ?? 'PCS';
                $binCode = $this->selectedBin->code;

                InventoryAdjustmentItem::create([
                    'inventory_adjustment_id' => $header->id,
                    'bin_id' => $this->selectedBin->id,
                    'item_variant_id' => $variant->id,
                    'system_qty' => $this->systemQty,
                    'physical_qty' => $this->actualQty,
                    'variance' => $this->difference,
                    'reason_code' => $this->reasonCode,
                    'notes' => $this->notes,
                    'status' => 'WAITING',
                    
                    // Immutable snapshots
                    'item_name_snapshot' => $itemName,
                    'erp_code_snapshot' => $erpCode,
                    'bin_code_snapshot' => $binCode,
                    'unit_snapshot' => $unit,
                    'warehouse_name_snapshot' => $warehouseName,
                    'operator_name_snapshot' => $operatorName,
                ]);

                // Synchronize parent status
                InventoryAdjustment::synchronizeStatus($header->id);
            });

            session()->flash('message', 'Inventory adjustment berhasil dikirim ke Manager PPIC.');

        } else {
            // Case B: No Discrepancy (systemQty == actualQty) -> Direct operational log
            DB::transaction(function () {
                // 1. Get or Create Daily Opname Session
                $opnameCode = 'OPN-' . date('Ymd');
                $opname = StockOpname::firstOrCreate(
                    ['code' => $opnameCode],
                    [
                        'scope_type' => 'LOCATION',
                        'scope_id' => $this->selectedBin->location_id,
                        'status' => 'DRAFT',
                        'created_by' => (string)auth()->id()
                    ]
                );

                // 2. Create Opname Item Record
                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'bin_id' => $this->selectedBin->id,
                    'system_qty' => $this->systemQty,
                    'actual_qty' => $this->actualQty,
                    'difference' => $this->difference,
                ]);

                // 3. Update Variant Last Opname (Only for matching count since it's verified)
                $this->selectedBin->itemVariant->update([
                    'last_opname_at' => now()
                ]);
            });

            session()->flash('message', 'Opname record saved successfully.');
        }

        $this->resetAudit();
        $this->dispatch('focus-scanner');
    }

    public function render()
    {
        $reasons = AdjustmentReasonMaster::where('is_active', true)->orderBy('id')->get();

        return view('livewire.opname.opname-page', [
            'reasons' => $reasons
        ])->layout('layouts.app');
    }
}
