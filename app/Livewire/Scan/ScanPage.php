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

    public function mount()
    {
        // Session Persistence Load
        $this->cart = session()->get('scan_cart', []);
    }

    public function updatedBarcode()
    {
        // Support lazy reactivity if user stops typing (Fallback)
        $this->handleScan();
    }

    public function handleScan()
    {
        $this->message = '';
        
        if (empty(trim($this->barcode))) {
            $this->currentItem = null;
            return;
        }

        // Find item variant
        $variant = ItemVariant::with('item')->where('barcode', $this->barcode)->first();

        if ($variant) {
            $this->currentItem = $variant;
            $this->qty = 1;
        } else {
            $this->currentItem = null;
            $this->showMessage("Item not found for barcode: {$this->barcode}", 'error');
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
        $totalStockAvailable = Bin::where('item_variant_id', $this->currentItem->id)->sum('current_qty');

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
            ];
        }

        // Save session state
        $this->persistCart();

        // Reset inputs
        $this->barcode = '';
        $this->currentItem = null;
        $this->qty = 1;
        $this->showMessage('Item added to cart.', 'success');
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

        if (empty($this->cart)) {
            $this->showMessage('Cart is empty. Cannot submit.', 'error');
            return;
        }

        try {
            // Use broader transaction layer so entire cart succeeds or fails together
            DB::transaction(function () use ($inventoryService) {
                foreach ($this->cart as $item) {
                    $remainingQtyToFulfill = $item['qty'];

                    // Bin Selection: Fetch bins holding this item ordering by highest capacity so
                    // we pull from as few fragmented bins as mathematically possible.
                    $bins = Bin::where('item_variant_id', $item['item_variant_id'])
                               ->where('current_qty', '>', 0)
                               ->orderByDesc('current_qty')
                               ->get();

                    foreach ($bins as $bin) {
                        if ($remainingQtyToFulfill <= 0) break; // Finished fulfilling row

                        $qtyToTake = min($remainingQtyToFulfill, $bin->current_qty);
                        
                        // Execute transaction for this portion
                        $inventoryService->moveStock($bin, $qtyToTake, 'OUT', 'Scan_UI_Livewire');
                        $remainingQtyToFulfill -= $qtyToTake;
                    }

                    // Strict final assurance error handler
                    if ($remainingQtyToFulfill > 0) {
                        throw new \Exception("System Error: Critical stock shortage calculating total items dynamically for {$item['name']}. Transaction aborted.");
                    }
                }
            });

            // After flawless transaction: Clear cart and reset application state
            $this->cart = [];
            $this->persistCart();
            $this->barcode = '';
            $this->currentItem = null;
            $this->qty = 1;
            
            $this->showMessage('Transaction successfully pushed to inventory!', 'success');

        } catch (\Exception $e) {
            $this->showMessage($e->getMessage(), 'error');
        }
    }

    private function showMessage($text, $type)
    {
        $this->message = $text;
        $this->messageType = $type;
    }

    public function render()
    {
        return view('livewire.scan.scan-page');
    }
}
