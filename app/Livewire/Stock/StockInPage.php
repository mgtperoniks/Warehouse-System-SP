<?php

namespace App\Livewire\Stock;

use App\Models\Bin;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemVariant;
use App\Models\Supplier;
use App\Services\Barcode\BarcodeService;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class StockInPage extends Component
{
    // State Properties
    public $barcode = '';
    public $currentItem = null;
    public $qty = 1;
    public $bin_id = null;
    public $supplier_id = null;
    public $last_used_bin_id = null;
    public $reference = '';
    public $cart = [];
    public $lastAction = '';
    public $autoAddMode = false;

    // New Item Flow
    public $isNewItem = false;
    public $erpCode = '';
    public $itemName = '';

    protected $listeners = ['focus-barcode-input' => 'focusInput'];

    public function mount()
    {
        $this->cart = session()->get('stock_in_cart', []);
        $this->last_used_bin_id = session()->get('stock_in_last_bin');
        $this->autoAddMode = session()->get('stock_in_auto_add', false);
    }

    public function updatedAutoAddMode($value)
    {
        session()->put('stock_in_auto_add', $value);
    }

    public function handleScan()
    {
        if (empty($this->barcode)) return;

        $barcodeService = new BarcodeService();
        $this->barcode = $barcodeService->normalize($this->barcode);

        $barcodeObj = ItemBarcode::with(['variant.item', 'variant.suppliers'])
            ->where('barcode', $this->barcode)
            ->first();

        if ($barcodeObj && $barcodeObj->variant) {
            $this->currentItem = $barcodeObj->variant;
            $this->isNewItem = false;

            // Auto-select first supplier if exists and current supplier is null
            if (!$this->supplier_id && $this->currentItem->suppliers->isNotEmpty()) {
                $this->supplier_id = $this->currentItem->suppliers->first()->id;
            }

            // Auto-fill bin
            if ($this->last_used_bin_id) {
                $this->bin_id = $this->last_used_bin_id;
            }

            if ($this->autoAddMode) {
                $this->addToCart();
            } else {
                $this->dispatch('scan-success');
            }
        } else {
            $this->currentItem = null;
            $this->isNewItem = true;
            $this->erpCode = '';
            $this->itemName = '';
        }
    }

    public function createNewItem()
    {
        $this->validate([
            'erpCode' => 'required|unique:item_variants,erp_code',
            'itemName' => 'required|min:3',
        ]);

        DB::transaction(function () {
            $item = Item::create(['name' => $this->itemName]);
            
            $variant = ItemVariant::create([
                'item_id' => $item->id,
                'erp_code' => $this->erpCode,
                'sku' => $this->erpCode,
            ]);

            ItemBarcode::create([
                'item_variant_id' => $variant->id,
                'barcode' => $this->barcode,
                'type' => 'SUPPLIER',
                'is_primary' => true,
            ]);

            $this->currentItem = $variant->load('item', 'suppliers');
            $this->isNewItem = false;
        });

        if ($this->autoAddMode) {
            $this->addToCart();
        }
    }

    public function addToCart()
    {
        if (!$this->currentItem) return;

        if (!$this->bin_id) {
            $this->addError('bin_id', 'Destination bin is required.');
            return;
        }

        $this->validate([
            'qty' => 'required|integer|min:1',
            'bin_id' => 'required|exists:bins,id',
        ]);

        $variantId = $this->currentItem->id;
        $binId = $this->bin_id;
        $supplierId = $this->supplier_id;

        // Strict aggregation key
        $key = $variantId . '-' . $binId . '-' . ($supplierId ?? 'none');

        if (isset($this->cart[$key])) {
            $this->cart[$key]['qty'] += $this->qty;
        } else {
            $bin = Bin::find($binId);
            $supplier = $supplierId ? Supplier::find($supplierId) : null;

            $this->cart[$key] = [
                'item_variant_id' => $variantId,
                'name' => $this->currentItem->item->name,
                'erp_code' => $this->currentItem->erp_code,
                'qty' => $this->qty,
                'bin_id' => $binId,
                'bin_name' => $bin->code,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplier ? $supplier->name : 'N/A',
            ];
        }

        $this->lastAction = "+{$this->qty} " . $this->currentItem->item->name . " added to " . $this->cart[$key]['bin_name'];
        $this->last_used_bin_id = $binId;
        
        $this->persistCart();
        
        // UX Reset
        $this->barcode = '';
        $this->currentItem = null;
        $this->qty = 1;
        
        $this->dispatch('scan-completed');
        $this->dispatch('focus-barcode-input');
    }

    public function removeFromCart($key)
    {
        unset($this->cart[$key]);
        $this->persistCart();
    }

    private function persistCart()
    {
        session()->put('stock_in_cart', $this->cart);
        session()->put('stock_in_last_bin', $this->last_used_bin_id);
    }

    public function submit(InventoryService $inventoryService)
    {
        if (empty($this->cart)) return;

        try {
            DB::transaction(function () use ($inventoryService) {
                foreach ($this->cart as $item) {
                    $bin = Bin::findOrFail($item['bin_id']);
                    
                    $inventoryService->moveStock(
                        $bin,
                        $item['qty'],
                        'IN',
                        $this->reference ?: 'Manual Stock IN',
                        auth()->id(), // created_by
                        $item['supplier_id']
                    );
                }
            });

            // Success Cleanup
            $this->cart = [];
            $this->persistCart();
            $this->reset(['barcode', 'currentItem', 'qty', 'bin_id', 'supplier_id', 'lastAction', 'reference']);
            
            $this->dispatch('message-dispatched', message: 'Stock successfully committed to inventory!', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('message-dispatched', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
    }

    public function generateInternalBarcode()
    {
        if (!$this->currentItem) return;
        
        $service = new BarcodeService();
        $barcode = $service->generateInternalBarcodeForVariant($this->currentItem->id);
        
        $this->dispatch('message-dispatched', message: "New internal barcode generated: {$barcode}", type: 'success');
    }

    public function render()
    {
        return view('livewire.stock.stock-in-page', [
            'bins' => Bin::orderBy('code')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }
}
