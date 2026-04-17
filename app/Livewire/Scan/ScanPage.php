<?php

namespace App\Livewire\Scan;

use App\Models\ItemVariant;
use App\Models\Bin;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ScanPage extends Component
{
    public $barcode = '';
    public $currentItem = null;
    public $qty = 1;
    public $cart = [];
    public $message = '';
    public $messageType = '';
    public $lastAction = '';

    // Transaction Header
    public $deptId = null;
    public $picId = null;
    public $reference = '';
    
    // Dropdown Data (Moved to render to optimize performance)
    
    // Post-Submit State
    public $isSubmitted = false;
    public $lastTransactionId = null;
    public $lastTransactionCode = '';

    public function mount()
    {
        // Session Persistence Load
        $this->cart = session()->get('scan_cart', []);
    }

    public function updatedDeptId($value)
    {
        $this->picId = null; // Reset PIC when department changes
    }

    public function updatedBarcode()
    {
        // Support lazy reactivity if user stops typing (Fallback)
        if (!empty(trim($this->barcode))) {
            $this->handleScan();
        }
    }

    public function handleScan()
    {
        $this->message = '';
        
        if (empty(trim($this->barcode))) {
            $this->currentItem = null;
            return;
        }

        // Find item barcode with eager loaded relationships for fast UI rendering
        $barcodeObj = \App\Models\ItemBarcode::with(['variant.item', 'variant.images'])
            ->where('barcode', trim($this->barcode))
            ->first();

        if ($barcodeObj && $barcodeObj->variant && $barcodeObj->variant->item) {
            $this->currentItem = $barcodeObj->variant;
            $this->qty = 1;

            // Auto Flow: Call addToCart if qty = 1
            if ($this->qty == 1) {
                $this->addToCart();
            }
        } else {
            $this->currentItem = null;
            $this->showMessage("Barcode not recognized: {$this->barcode}. Ensure it is registered in Master Data.", 'error');
        }
    }

    public function addToCart()
    {
        $this->message = '';

        if (!$this->currentItem) {
            $this->showMessage('Please scan a valid item first.', 'error');
            return;
        }

        $requestedQty = (int) $this->qty;

        if ($requestedQty <= 0) {
            $this->showMessage('Quantity must be greater than zero.', 'error');
            return;
        }

        // Cart Aggregation: Check if item already exists in cart mapped by variant id
        $existingQtyInCart = 0;
        $existingIndex = null;

        foreach ($this->cart as $index => $item) {
            if ($item['item_variant_id'] === $this->currentItem->id) {
                $existingQtyInCart = $item['qty'];
                $existingIndex = $index;
                break;
            }
        }

        $totalRequested = $requestedQty + $existingQtyInCart;

        // Stock Validation: Calculate total available stock across all bins
        $totalStockAvailable = \App\Models\Bin::where('item_variant_id', $this->currentItem->id)->sum('current_qty');

        if ($totalRequested > $totalStockAvailable) {
            $this->showMessage("Request exceeds available stock. Only {$totalStockAvailable} in total inventory (Cart currently holds: {$existingQtyInCart}).", 'error');
            return;
        }

        if ($existingIndex !== null) {
            // Aggregate quantity
            $this->cart[$existingIndex]['qty'] = $totalRequested;
        } else {
            // Push new array entity
            $this->cart[] = [
                'item_variant_id' => $this->currentItem->id,
                'name'            => $this->currentItem->item->name . ' - ' . $this->currentItem->sku,
                'qty'             => $requestedQty,
                'barcode'         => $this->barcode,
                'price'           => $this->currentItem->price ?? 0,
                'unit'            => $this->currentItem->unit,
                'erp_code'        => $this->currentItem->erp_code,
            ];
        }

        // Save session state
        $this->persistCart();

        // Reset inputs
        $this->lastAction = "+{$requestedQty} " . ($this->currentItem->item->name ?? 'Unknown Item');
        $this->barcode = '';
        $this->currentItem = null;
        $this->qty = 1;

        $this->showMessage('Item added to cart.', 'success');
        
        // Dispatch feedback & focus events
        $this->dispatch('scan-completed');
        $this->dispatch('focus-barcode-input');
    }

    public function removeFromCart($index)
    {
        if (isset($this->cart[$index])) {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart); // Re-index securely
            $this->persistCart();
        }
    }

    private function persistCart()
    {
        session()->put('scan_cart', $this->cart);
    }

    public function submit(InventoryService $inventoryService)
    {
        $this->message = '';

        // 1. Validation
        if (empty($this->cart)) {
            $this->showMessage('Cart is empty. Cannot submit.', 'error');
            return;
        }

        if (!$this->deptId || !$this->picId) {
            $this->showMessage('Please select Department and PIC.', 'error');
            return;
        }

        try {
            $transaction = DB::transaction(function () use ($inventoryService) {
                // Calculate Total Price for the header
                $totalTransactionPrice = collect($this->cart)->sum(fn($item) => $item['qty'] * $item['price']);

                // 2. Create Stock Transaction Header
                $trx = \App\Models\StockTransaction::create([
                    'code'          => \App\Models\StockTransaction::generateCode(),
                    'type'          => 'OUT',
                    'status'        => 'CONFIRMED',
                    'department_id' => $this->deptId,
                    'user_id'       => $picId = $this->picId,
                    'reference'     => $this->reference,
                    'total_price'   => $totalTransactionPrice,
                ]);

                foreach ($this->cart as $item) {
                    $remainingQtyToFulfill = $item['qty'];

                    // 3. Find Bins & Deduct Stock
                    $bins = \App\Models\Bin::where('item_variant_id', $item['item_variant_id'])
                               ->where('current_qty', '>', 0)
                               ->orderByDesc('current_qty')
                               ->get();

                    foreach ($bins as $bin) {
                        if ($remainingQtyToFulfill <= 0) break;

                        $qtyToTake = min($remainingQtyToFulfill, $bin->current_qty);
                        
                        // Execute movement
                        $inventoryService->moveStock($bin, $qtyToTake, 'OUT', $trx->code, auth()->id());
                        
                        // 4. Create Transaction Item Record (including snapshots)
                        \App\Models\StockTransactionItem::create([
                            'stock_transaction_id' => $trx->id,
                            'item_variant_id'      => $item['item_variant_id'],
                            'bin_id'               => $bin->id,
                            'qty'                  => $qtyToTake,
                            'item_name_snapshot'   => $item['name'],
                            'erp_code_snapshot'    => $item['erp_code'],
                            'unit_snapshot'        => $item['unit'],
                            'price_snapshot'       => $item['price'],
                            'total_price_snapshot' => $qtyToTake * $item['price'],
                        ]);

                        $remainingQtyToFulfill -= $qtyToTake;
                    }

                    if ($remainingQtyToFulfill > 0) {
                        $taken = $item['qty'] - $remainingQtyToFulfill;
                        throw new \Exception("Insufficient stock to fulfill {$item['name']}. Only {$taken} taken.");
                    }
                }

                return $trx;
            });

            // 5. Success State
            $this->lastTransactionId = $transaction->id;
            $this->lastTransactionCode = $transaction->code;
            $this->isSubmitted = true;
            
            $this->cart = [];
            $this->persistCart();
            $this->barcode = '';
            $this->currentItem = null;
            $this->qty = 1;
            $this->reference = '';
            
            $this->showMessage('Transaction successfully completed!', 'success');

        } catch (\Exception $e) {
            $this->showMessage($e->getMessage(), 'error');
        }
    }

    public function resetSession()
    {
        $this->isSubmitted = false;
        $this->lastTransactionId = null;
        $this->lastTransactionCode = '';
        $this->deptId = null;
        $this->picId = null;
        $this->availablePics = [];
        $this->dispatch('focus-barcode-input');
    }

    private function showMessage($text, $type)
    {
        $this->message = $text;
        $this->messageType = $type;
    }

    public function render()
    {
        return view('livewire.scan.scan-page', [
            'departments' => \App\Models\Department::orderBy('name')->get(),
            'availablePics' => $this->deptId 
                ? \App\Models\User::where('department_id', $this->deptId)->orderBy('name')->get() 
                : []
        ]);
    }
}
