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

    // Search Mode Properties (Consumable)
    public $searchQuery = '';
    public $searchResults = [];

    // Transaction Header
    public $deptId = null;
    public $picId = null;
    public $reference = '';
    
    // Post-Submit State
    public $isSubmitted = false;
    public $lastTransactionId = null;
    public $lastTransactionCode = '';

    public function mount()
    {
        // Session Persistence Load
        $this->cart = session()->get('scan_cart', []);
    }

    public function getInputModeProperty(): string
    {
        $warehouseId = session('active_warehouse_id');
        $warehouse = null;

        if ($warehouseId) {
            $warehouse = \App\Models\Warehouse::find($warehouseId);
        } elseif (session('active_warehouse_code')) {
            $warehouse = \App\Models\Warehouse::where('code', session('active_warehouse_code'))->first();
        }

        if ($warehouse) {
            $code = strtoupper(trim($warehouse->code));
            $name = strtoupper(trim($warehouse->name));
            if ($code === 'CONSUMABLE' || str_contains($name, 'CONSUMABLE')) {
                return 'search';
            }
        }

        return 'barcode';
    }

    public function updatedSearchQuery()
    {
        $q = trim($this->searchQuery);
        if (strlen($q) < 1) {
            $this->searchResults = [];
            return;
        }

        // ActiveWarehouseDomainScope automatically isolates items to current warehouse domain
        $this->searchResults = ItemVariant::with(['item', 'bins', 'images', 'primaryBarcode'])
            ->where(function ($query) use ($q) {
                $query->where('erp_code', 'like', "%{$q}%")
                      ->orWhere('sku', 'like', "%{$q}%")
                      ->orWhereHas('item', function ($iq) use ($q) {
                          $iq->where('name', 'like', "%{$q}%");
                      })
                      ->orWhereHas('barcodes', function ($bq) use ($q) {
                          $bq->where('barcode', 'like', "%{$q}%");
                      });
            })
            ->limit(15)
            ->get()
            ->map(fn($v) => [
                'id' => $v->id,
                'name' => $v->item->name ?? 'Unknown Item',
                'sku' => $v->sku,
                'erp_code' => $v->erp_code,
                'unit' => $v->unit,
                'price' => $v->price ?? 0,
                'stock' => (int) $v->bins->sum('current_qty'),
            ])
            ->toArray();
    }

    public function selectSearchResult($variantId, $qty = 1)
    {
        $variant = ItemVariant::with(['item', 'images', 'bins', 'primaryBarcode'])->find($variantId);
        if (!$variant) {
            $this->showMessage('Selected item not found or unavailable in this warehouse.', 'error');
            return;
        }

        $qtyVal = (int) $qty;
        if ($qtyVal < 1) {
            $qtyVal = 1;
        }

        $this->addVariantToCart($variant, $qtyVal);

        $this->searchQuery = '';
        $this->searchResults = [];
    }

    public function updatedDeptId($value)
    {
        $this->picId = null; // Reset PIC when department changes
    }

    public function updatedBarcode()
    {
        if (!empty(trim($this->barcode))) {
            $this->submitScan();
        }
    }

    protected $listeners = ['barcode-scanned' => 'submitScan'];

    public function logTakeover($previousOwner, $newOwner, $terminalId, $takeoverReason = 'Manual Operator Override')
    {
        \App\Models\WmsTerminalTakeoverLog::create([
            'workflow' => 'stock_out',
            'terminal_id' => $terminalId,
            'previous_owner' => $previousOwner ?: 'UNKNOWN',
            'new_owner' => $newOwner ?: auth()->user()->name,
            'takeover_reason' => $takeoverReason,
        ]);
        
        $this->dispatch('takeover-logged');
    }

    /**
     * 📥 Unified Ingestion Engine Pipeline
     * Canonical single entry point for all scan sources (wedged scanners, manual typing, and Alpine events).
     */
    public function submitScan($barcode = null, $qty = null)
    {
        $this->message = '';
        $barcodeVal = '';
        $qtyVal = 1;

        // 1. Differentiate input source signatures
        if (is_array($barcode)) {
            // Case A: Alpine.js / Custom Scanner dispatch dictionary
            $barcodeVal = $barcode['barcode'] ?? '';
            $qtyVal = (int) ($barcode['qty'] ?? 1);
        } elseif ($barcode !== null) {
            // Case B: Direct method parameter call (typed manually or via camera) or Named Arguments
            $barcodeVal = (string) $barcode;
            $qtyVal = $qty !== null ? (int) $qty : 1;
        } else {
            // Case C: Standard model fallback (lazy keyboard inputs / form returns)
            $barcodeVal = (string) $this->barcode;
            $qtyVal = (int) ($this->qty ?: 1);
        }

        $barcodeVal = trim($barcodeVal);
        if (empty($barcodeVal)) {
            $this->currentItem = null;
            return;
        }

        // 2. Sanitize (strip control characters)
        $barcodeVal = trim(preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $barcodeVal));

        // 3. Parse shorthand formatting (e.g. BARCODE*QTY) if not parsed client-side
        if (!is_array($barcode) && str_contains($barcodeVal, '*')) {
            $match = [];
            if (preg_match('/^([a-zA-Z0-9.\-_]+)\*(\d+)$/', $barcodeVal, $match)) {
                $barcodeVal = $match[1];
                $qtyVal = (int) $match[2];
            } else {
                $msg = 'Invalid shorthand format. Use BARCODE*QTY (e.g. 1000000017*5).';
                $this->showMessage($msg, 'error');
                $this->dispatch('scan-failed', ['message' => $msg]);
                return;
            }
        }

        if (empty($barcodeVal) || $qtyVal <= 0) {
            $msg = 'Invalid barcode payload or quantity.';
            $this->showMessage($msg, 'error');
            $this->dispatch('scan-failed', ['message' => $msg]);
            return;
        }

        $this->barcode = $barcodeVal;
        $this->qty = $qtyVal;

        // 4. Resolve variant
        $barcodeObj = \App\Models\ItemBarcode::with(['variant.item', 'variant.images', 'variant.bins'])
            ->where('barcode', $this->barcode)
            ->first();

        if ($barcodeObj && $barcodeObj->variant && $barcodeObj->variant->item) {
            $this->addVariantToCart($barcodeObj->variant, $qtyVal, $this->barcode);
            $this->barcode = '';
            $this->qty = 1;
        } else {
            $this->currentItem = null;
            $this->barcode = '';
            $this->qty = 1;
            $msg = "Barcode not recognized: {$barcodeVal}. Ensure it is registered in Master Data.";
            $this->showMessage($msg, 'error');
            $this->dispatch('scan-failed', ['message' => $msg]);
        }
    }

    /**
     * 🛒 Shared Cart Ingestion Helper
     * Single canonical method for adding ItemVariant to the Active Batch (used by both Barcode & Consumable Search).
     */
    public function addVariantToCart(ItemVariant $variant, int $requestedQty = 1, ?string $barcode = null)
    {
        if (!$variant || !$variant->item) {
            $this->showMessage('Item variant invalid or not found.', 'error');
            return false;
        }

        $this->currentItem = $variant;
        $barcodeVal = $barcode ?? $variant->primaryBarcode?->barcode ?? $variant->sku;

        // Stock availability validations
        $existingIndex = null;
        $existingQtyInCart = 0;
        foreach ($this->cart as $index => $item) {
            if ($item['item_variant_id'] === $variant->id) {
                $existingQtyInCart = $item['qty'];
                $existingIndex = $index;
                break;
            }
        }

        $totalRequested = $requestedQty + $existingQtyInCart;
        $totalStockAvailable = \App\Models\Bin::where('item_variant_id', $variant->id)->sum('current_qty');

        if ($totalRequested > $totalStockAvailable) {
            $this->currentItem = null;
            $msg = "Request exceeds available stock. Only {$totalStockAvailable} in total inventory (Cart currently holds: {$existingQtyInCart}).";
            $this->showMessage($msg, 'error');
            $this->dispatch('scan-failed', ['message' => $msg]);
            return false;
        }

        if ($existingIndex !== null) {
            $this->cart[$existingIndex]['qty'] = $totalRequested;
        } else {
            $this->cart[] = [
                'item_variant_id' => $variant->id,
                'name'            => $variant->item->name . ' - ' . $variant->sku,
                'qty'             => $requestedQty,
                'barcode'         => $barcodeVal,
                'price'           => $variant->price ?? 0,
                'unit'            => $variant->unit,
                'erp_code'        => $variant->erp_code,
            ];
        }

        $this->persistCart();

        $this->lastAction = "+{$requestedQty} " . ($variant->item->name ?? 'Unknown Item');
        
        $primaryPhoto = $variant->images->where('is_primary', true)->first();
        $photoUrl = $primaryPhoto ? asset('storage/' . $primaryPhoto->path) : asset('images/placeholders/item.svg');
        $primaryBin = $variant->bins()->first()?->code ?? 'N/A';
        $remainingStock = $totalStockAvailable - $totalRequested;

        $this->dispatch('scan-success', [
            'name' => $variant->item->name,
            'sku' => $variant->sku,
            'qty' => $requestedQty,
            'photo' => $photoUrl,
            'bin' => $primaryBin,
            'remaining' => max(0, $remainingStock),
            'unit' => $variant->unit
        ]);

        $this->currentItem = null;
        return true;
    }

    public function removeFromCart($index)
    {
        if (isset($this->cart[$index])) {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart);
            $this->persistCart();
            if ($this->inputMode === 'barcode') {
                $this->dispatch('focus-barcode-input');
            }
        }
    }

    public function updateCartQty($index, $qty)
    {
        if (isset($this->cart[$index])) {
            $qtyVal = (int) $qty;
            if ($qtyVal < 1) {
                $qtyVal = 1;
            }

            $itemVariantId = $this->cart[$index]['item_variant_id'];
            $totalStockAvailable = \App\Models\Bin::where('item_variant_id', $itemVariantId)->sum('current_qty');

            if ($qtyVal > $totalStockAvailable) {
                $qtyVal = $totalStockAvailable;
                $this->showMessage("Clamped quantity to available stock limit ({$totalStockAvailable}).", 'error');
            }

            $this->cart[$index]['qty'] = $qtyVal;
            $this->persistCart();
            if ($this->inputMode === 'barcode') {
                $this->dispatch('focus-barcode-input');
            }
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

        // Idempotency: Get atomic lock for the current operator to prevent double click/retries
        $lockKey = 'stock-out-lock-' . auth()->id();
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            $this->showMessage('Your checkout request is already being processed. Please wait.', 'error');
            return;
        }

        try {
            $transaction = DB::transaction(function () use ($inventoryService) {
                // Re-read cart inside transaction for absolute safety against concurrent requests
                $currentCart = session()->get('scan_cart', []);
                if (empty($currentCart)) {
                    throw new \Exception("Cart is empty or has already been processed.");
                }

                // 2. Pre-compile all bin deductions (allocations)
                $allocations = [];
                foreach ($currentCart as $item) {
                    $remainingQtyToFulfill = $item['qty'];

                    // Find Bins
                    $bins = \App\Models\Bin::where('item_variant_id', $item['item_variant_id'])
                               ->where('current_qty', '>', 0)
                               ->orderByDesc('current_qty')
                               ->get();

                    foreach ($bins as $bin) {
                        if ($remainingQtyToFulfill <= 0) break;

                        $qtyToTake = min($remainingQtyToFulfill, $bin->current_qty);
                        $allocations[] = [
                            'bin' => $bin,
                            'qty' => $qtyToTake,
                            'item' => $item
                        ];
                        $remainingQtyToFulfill -= $qtyToTake;
                    }

                    if ($remainingQtyToFulfill > 0) {
                        $taken = $item['qty'] - $remainingQtyToFulfill;
                        throw new \Exception("Insufficient stock to fulfill {$item['name']}. Only {$taken} taken.");
                    }
                }

                // 3. Deadlock Prevention: Sort compiled allocations by bin_id ASC
                usort($allocations, function ($a, $b) {
                    return $a['bin']->id <=> $b['bin']->id;
                });

                // Generate code inside lock & transaction to prevent unique code conflicts
                $trxCode = \App\Models\StockTransaction::generateCode();

                // 4. Create Stock Transaction Header
                $trx = \App\Models\StockTransaction::create([
                    'code'                => $trxCode,
                    'type'                => 'OUT',
                    'status'              => 'CONFIRMED',
                    'department_id'       => $this->deptId,
                    'user_id'             => $this->picId,
                    'reference'           => $this->reference,
                    'total_price'         => collect($currentCart)->sum(fn($item) => $item['qty'] * $item['price']),
                    'warehouse_id'        => session('active_warehouse_id'),
                    'operator_id'         => auth()->id(),
                    'terminal_id'         => session('wms_terminal_id') ?: 'SPAREPART-DESK-A',
                    'terminal_session_id' => session()->getId(),
                ]);

                // 5. Execute stock movements and create transaction items in deterministic sorted order
                foreach ($allocations as $alloc) {
                    $bin = $alloc['bin'];
                    $qtyToTake = $alloc['qty'];
                    $item = $alloc['item'];

                    // Execute movement
                    $inventoryService->moveStock($bin, $qtyToTake, 'OUT', $trx->code, auth()->id());
                    
                    // Create Transaction Item Record
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
                }

                // Clear the session cart inside the transaction
                session()->forget('scan_cart');
                return $trx;
            });

            // 6. Success State
            $this->cart = [];
            $this->barcode = '';
            $this->currentItem = null;
            $this->qty = 1;
            $this->reference = '';
            
            return redirect()->route('reports.stock-out.preview', ['code' => $transaction->code]);
        } catch (\Exception $e) {
            $this->showMessage($e->getMessage(), 'error');
        } finally {
            $lock->release();
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
        if ($this->inputMode === 'barcode') {
            $this->dispatch('focus-barcode-input');
        }
    }

    private function showMessage($text, $type)
    {
        $this->message = $text;
        $this->messageType = $type;
    }

    public function render()
    {
        return view('livewire.scan.scan-page', [
            'departments' => \App\Models\Department::where('is_active', true)->orderBy('name')->get(),
            'availablePics' => $this->deptId 
                ? \App\Models\User::where('department_id', $this->deptId)->where('is_active', true)->orderBy('name')->get() 
                : []
        ]);
    }
}
