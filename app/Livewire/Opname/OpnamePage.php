<?php

namespace App\Livewire\Opname;

use Livewire\Component;

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

    public function updatedBinScan($value)
    {
        $this->handleScan($value);
    }

    public function handleScan($value)
    {
        if (empty($value)) return;

        $this->resetAudit();

        // 1. Try to find Bin directly by code
        $bin = \App\Models\Bin::where('code', $value)->first();
        if ($bin) {
            $this->loadBin($bin);
            return;
        }

        // 2. Try to find by Item Barcode
        $barcode = \App\Models\ItemBarcode::where('barcode', $value)->first();
        if ($barcode) {
            $variant = $barcode->variant;
            $bins = $variant->bins;

            if ($bins->count() === 0) {
                $this->addError('binScan', 'Item found but has no assigned bins.');
            } elseif ($bins->count() === 1) {
                $this->loadBin($bins->first());
            } else {
                $this->candidateBins = $bins;
                $this->isSelectingBin = true;
                $this->isScanning = false;
            }
            return;
        }

        $this->addError('binScan', 'No bin or item found with this code.');
    }

    public function selectBin($binId)
    {
        $bin = \App\Models\Bin::find($binId);
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
    }

    public function updatedActualQty($value)
    {
        $this->difference = (int)$value - $this->systemQty;
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

        \Illuminate\Support\Facades\DB::transaction(function () {
            // 1. Get or Create Daily Opname Session
            $opnameCode = 'OPN-' . date('Ymd');
            $opname = \App\Models\StockOpname::firstOrCreate(
                ['code' => $opnameCode],
                [
                    'scope_type' => 'LOCATION', // Default scope
                    'scope_id' => $this->selectedBin->location_id,
                    'status' => 'DRAFT',
                    'created_by' => (string)auth()->id()
                ]
            );

            // 2. Create Opname Item Record
            \App\Models\StockOpnameItem::create([
                'stock_opname_id' => $opname->id,
                'bin_id' => $this->selectedBin->id,
                'system_qty' => $this->systemQty,
                'actual_qty' => $this->actualQty,
                'difference' => $this->difference,
            ]);

            // 3. Handle Adjustment if needed
            if ($this->difference !== 0) {
                $inventoryService = app(\App\Services\Inventory\InventoryService::class);
                $inventoryService->moveStock(
                    $this->selectedBin,
                    $this->difference,
                    'ADJUSTMENT',
                    'Stock Opname Correction: ' . $opname->code,
                    (string)auth()->id()
                );
            }

            // 4. Update Variant Last Opname
            $this->selectedBin->itemVariant->update([
                'last_opname_at' => now()
            ]);
        });

        session()->flash('message', 'Opname record saved successfully.');
        $this->resetAudit();
        $this->dispatch('focus-scanner');
    }

    public function render()
    {
        return view('livewire.opname.opname-page')->layout('layouts.app');
    }
}
