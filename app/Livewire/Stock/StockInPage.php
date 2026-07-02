<?php

namespace App\App\Livewire\Stock; // Fallback or direct namespace

namespace App\Livewire\Stock;

use App\Models\Bin;
use App\Models\Item;
use App\Models\ItemBarcode;
use App\Models\ItemVariant;
use App\Models\Supplier;
use App\Models\StockInReceipt;
use App\Models\StockInItem;
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
    public $binCode = ''; // For model bind
    public $supplier_id = null;
    public $last_used_bin_id = null;
    public $binAutoAssigned = false;
    public $reference = '';
    public $cart = [];
    public $lastAction = '';
    public $autoAddMode = false;
    public $recommendedBinCode = null;

    // Single-Session & Persistence Properties
    public $showResumeModal = false;
    public $activeReceipt = null;

    // New Item Flow
    public $isNewItem = false;
    public $erpCode = '';
    public $itemName = '';

    protected $listeners = [
        'barcode-scanned' => 'submitScan',
        'focus-barcode-input' => 'focusInput',
        'logTakeover' => 'logTakeover'
    ];

    public function logTakeover($previousOwner, $newOwner, $terminalId, $takeoverReason = 'Manual Operator Override')
    {
        \App\Models\WmsTerminalTakeoverLog::create([
            'workflow' => 'stock_in',
            'terminal_id' => $terminalId,
            'previous_owner' => $previousOwner ?: 'UNKNOWN',
            'new_owner' => $newOwner ?: auth()->user()->name,
            'takeover_reason' => $takeoverReason,
        ]);
        
        $this->dispatch('takeover-logged');
    }

    public function mount()
    {
        $this->autoAddMode = session()->get('stock_in_auto_add', false);
        $this->last_used_bin_id = session()->get('stock_in_last_bin');

        // Single Active Session Operator Check (Scoped to active warehouse)
        $existing = StockInReceipt::forActiveWarehouse()
            ->where('user_id', auth()->id())
            ->where('status', 'ACTIVE')
            ->first();

        if ($existing) {
            $this->activeReceipt = $existing;
            $this->showResumeModal = true;
        } else {
            $this->createNewReceiptSession();
        }
    }

    public function updatedAutoAddMode($value)
    {
        session()->put('stock_in_auto_add', $value);
    }

    public function updatedQty($value)
    {
        if ($value !== null && $value !== '') {
            $intValue = (int)$value;
            if ($intValue < 1) {
                $this->qty = 1;
            } else {
                $this->qty = $intValue;
            }
        }
    }

    public function updatedBinCode($value)
    {
        if ($value) {
            $bin = Bin::forActiveWarehouse()->where('code', $value)->first();
            if ($bin) {
                $this->bin_id = $bin->id;
            }
        } else {
            $this->bin_id = null;
        }
    }

    public function createNewReceiptSession()
    {
        $this->activeReceipt = StockInReceipt::create([
            'receipt_code' => 'IN-' . auth()->id() . '-' . time(),
            'user_id' => auth()->id(),
            'status' => 'ACTIVE',
            'last_activity_at' => now(),
            'warehouse_id' => session('active_warehouse_id'),
            'operator_id' => auth()->id(),
            'terminal_id' => session('wms_terminal_id') ?: 'SPAREPART-DESK-A',
            'terminal_session_id' => session()->getId(),
        ]);

        $this->loadCartFromActiveReceipt();
    }

    public function resumeSession()
    {
        if ($this->activeReceipt) {
            $this->activeReceipt->update(['last_activity_at' => now()]);
            $this->reference = $this->activeReceipt->purchase_order_ref ?? '';
            $this->supplier_id = $this->activeReceipt->supplier_id;
            $this->loadCartFromActiveReceipt();
        }
        $this->showResumeModal = false;
        $this->dispatch('message-dispatched', message: 'Previous session resumed successfully!', type: 'success');
        $this->dispatch('focus-barcode-input');
    }

    public function discardSession()
    {
        if ($this->activeReceipt) {
            $this->activeReceipt->update(['status' => 'ABANDONED', 'last_activity_at' => now()]);
        }

        $this->createNewReceiptSession();
        $this->showResumeModal = false;
        $this->reference = '';
        $this->supplier_id = null;
        $this->dispatch('message-dispatched', message: 'Previous session discarded. New session started.', type: 'success');
        $this->dispatch('focus-barcode-input');
    }

    public function loadCartFromActiveReceipt()
    {
        if (!$this->activeReceipt) {
            $this->cart = [];
            return;
        }

        $items = StockInItem::with(['variant.item', 'bin', 'supplier'])
            ->where('stock_in_receipt_id', $this->activeReceipt->id)
            ->get();

        $this->cart = [];
        foreach ($items as $item) {
            $key = $item->item_variant_id . '-' . ($item->bin_id ?? 'none') . '-' . ($item->supplier_id ?? 'none');
            $this->cart[$key] = [
                'id' => $item->id,
                'item_variant_id' => $item->item_variant_id,
                'name' => $item->variant->item->name,
                'erp_code' => $item->variant->erp_code,
                'qty' => $item->qty,
                'bin_id' => $item->bin_id,
                'bin_name' => $item->bin ? $item->bin->code : 'UNASSIGNED',
                'supplier_id' => $item->supplier_id,
                'supplier_name' => $item->supplier ? $item->supplier->name : 'N/A',
            ];
        }
    }

    /**
     * 📥 Unified Ingestion Engine Pipeline for Stock In
     */
    public function submitScan($barcode = null, $qty = null)
    {
        $barcodeVal = '';
        $qtyVal = 1;

        // 1. Differentiate input source signatures
        if (is_array($barcode)) {
            // Case A: Alpine.js / Custom Scanner dispatch dictionary
            $barcodeVal = $barcode['barcode'] ?? '';
            $qtyVal = (int) ($barcode['qty'] ?? 1);
        } elseif ($barcode !== null) {
            // Case B: Direct method parameter call or Named Arguments
            $barcodeVal = (string) $barcode;
            $qtyVal = $qty !== null ? (int) $qty : 1;
        } else {
            // Case C: Standard model fallback
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

        // 3. Parse shorthand formatting (BARCODE*QTY)
        if (!is_array($barcode) && str_contains($barcodeVal, '*')) {
            $match = [];
            if (preg_match('/^([a-zA-Z0-9.\-_]+)\*(\d+)$/', $barcodeVal, $match)) {
                $barcodeVal = $match[1];
                $qtyVal = (int) $match[2];
            } else {
                $msg = 'Invalid shorthand format. Use BARCODE*QTY (e.g. 1000000017*5).';
                $this->dispatch('scan-failed', ['message' => $msg]);
                return;
            }
        }

        if (empty($barcodeVal) || $qtyVal <= 0) {
            $msg = 'Invalid barcode payload or quantity.';
            $this->dispatch('scan-failed', ['message' => $msg]);
            return;
        }

        $this->barcode = $barcodeVal;
        $this->qty = $qtyVal;

        // Normalize
        $barcodeService = new BarcodeService();
        $this->barcode = $barcodeService->normalize($this->barcode);

        // 4. Resolve variant
        $barcodeObj = ItemBarcode::with(['variant.item', 'variant.suppliers', 'variant.images'])
            ->where('barcode', $this->barcode)
            ->first();

        if ($barcodeObj && $barcodeObj->variant) {
            $this->currentItem = $barcodeObj->variant;
            $this->isNewItem = false;

            // Auto-select first supplier if exists and current supplier is null
            if (!$this->supplier_id && $this->currentItem->suppliers->isNotEmpty()) {
                $this->supplier_id = $this->currentItem->suppliers->first()->id;
            }

            // Resolve true operational Main Location deterministically from item master metadata, ignoring historical/other warehouse bins
            $variantBins = Bin::where('item_variant_id', $this->currentItem->id)
                ->where(function ($q) {
                    $q->whereNull('warehouse_id')
                      ->orWhere('warehouse_id', session('active_warehouse_id'));
                })
                ->get();
            $mainBin = null;

            if ($variantBins->count() === 1) {
                // Deterministic: Single eligible bin exists
                $mainBin = $variantBins->first();
            } elseif ($variantBins->count() > 1) {
                // Multiple eligible bins exist: Replicate detail page logic by locating the primary/main global marker (where warehouse_id is null)
                $globalBins = $variantBins->filter(fn($b) => is_null($b->warehouse_id));
                if ($globalBins->count() === 1) {
                    $mainBin = $globalBins->first();
                }
            }

            $mainBinCode = $mainBin ? $mainBin->code : null;
            $this->recommendedBinCode = $mainBinCode;

            // Automatically resolve matching bin in active warehouse by exact code (only when exactly one match exists)
            $targetBin = null;
            if ($mainBinCode) {
                $activeWarehouseBins = Bin::forActiveWarehouse()
                    ->where('item_variant_id', $this->currentItem->id)
                    ->where('code', $mainBinCode)
                    ->get();

                if ($activeWarehouseBins->count() === 1) {
                    $targetBin = $activeWarehouseBins->first();
                }
            }

            // Auto-fill binCode immediately ONLY if exact match exists
            if ($targetBin) {
                $this->bin_id = $targetBin->id;
                $this->binCode = $targetBin->code;
                $this->binAutoAssigned = true;
            } else {
                $this->bin_id = null;
                $this->binCode = '';
                $this->binAutoAssigned = false;
            }

            if ($this->autoAddMode) {
                $this->addToCart();
            } else {
                $this->dispatch('scan-success', [
                    'name' => $this->currentItem->item->name,
                    'sku' => $this->currentItem->sku,
                    'photo' => $this->currentItem->images->where('is_primary', true)->first() 
                        ? asset('storage/' . $this->currentItem->images->where('is_primary', true)->first()->path) 
                        : asset('images/placeholders/item.svg'),
                    'bin' => $this->binCode ?: 'UNASSIGNED',
                    'qty' => $this->qty
                ]);
            }
        } else {
            $this->currentItem = null;
            $this->isNewItem = true;
            $this->erpCode = '';
            $this->itemName = '';
            $this->dispatch('scan-failed', ['message' => 'Barcode not registered in WMS database.']);
        }
    }

    public function createNewItem()
    {
        $this->validate([
            'erpCode' => 'required|unique:item_variants,erp_code',
            'itemName' => 'required|min:3',
        ]);

        // Validate ERP family belongs to active warehouse domain
        $domainService = app(\App\Services\Inventory\WarehouseDomainService::class);
        $family = $domainService->extractFamily($this->erpCode);
        if (!$domainService->belongsToActiveWarehouse($family)) {
            $activeWarehouseName = session('active_warehouse_name', 'Active Warehouse');
            $this->addError('erpCode', "ERP Family '{$family}' is not permitted for {$activeWarehouseName}.");
            return;
        }

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

            $this->currentItem = $variant->load('item', 'suppliers', 'images');
            $this->isNewItem = false;
        });

        if ($this->autoAddMode) {
            $this->addToCart();
        }
    }

    public function addToCart()
    {
        if (!$this->currentItem) return;

        $this->qty = max(1, (int) ($this->qty ?: 1));

        $this->validate([
            'qty' => 'required|integer|min:1',
        ]);

        if (!$this->bin_id) {
            $this->dispatch('message-dispatched', 
                message: 'Error: Cannot add to list. No location is assigned to this item in the active warehouse.', 
                type: 'error'
            );
            return;
        }

        $variantId = $this->currentItem->id;
        $binId = $this->bin_id ?: null;
        $supplierId = $this->supplier_id ?: null;

        // Sync to Database Draft items
        StockInItem::updateOrCreate([
            'stock_in_receipt_id' => $this->activeReceipt->id,
            'item_variant_id' => $variantId,
            'bin_id' => $binId,
            'supplier_id' => $supplierId ?: null,
        ], [
            'qty' => DB::raw("qty + {$this->qty}"),
        ]);

        // Touch the receipt timestamp & update metadata
        $this->activeReceipt->update([
            'last_activity_at' => now(),
            'supplier_id' => $this->supplier_id ?: null,
            'purchase_order_ref' => $this->reference ?: null
        ]);

        $bin = $binId ? Bin::find($binId) : null;
        $this->lastAction = "+{$this->qty} " . $this->currentItem->item->name . ($bin ? " added to " . $bin->code : " added");
        $this->last_used_bin_id = $binId;
        
        // Sync local cart from Database
        $this->loadCartFromActiveReceipt();

        session()->put('stock_in_last_bin', $this->last_used_bin_id);
        
        // UX Reset
        $this->barcode = '';
        $this->currentItem = null;
        $this->qty = 1;
        $this->recommendedBinCode = null;
        
        $this->dispatch('scan-completed');
        $this->dispatch('focus-barcode-input');
    }

    public function removeFromCart($key)
    {
        if (isset($this->cart[$key])) {
            $itemData = $this->cart[$key];
            
            $query = StockInItem::where('stock_in_receipt_id', $this->activeReceipt->id)
                ->where('item_variant_id', $itemData['item_variant_id']);

            if ($itemData['bin_id'] === null) {
                $query->whereNull('bin_id');
            } else {
                $query->where('bin_id', $itemData['bin_id']);
            }

            if ($itemData['supplier_id'] === null) {
                $query->whereNull('supplier_id');
            } else {
                $query->where('supplier_id', $itemData['supplier_id']);
            }

            $query->delete();

            unset($this->cart[$key]);

            if ($this->activeReceipt) {
                $this->activeReceipt->update(['last_activity_at' => now()]);
            }
        }
    }

    public function submit(InventoryService $inventoryService)
    {
        if (empty($this->cart) || !$this->activeReceipt) return;

        try {
            DB::transaction(function () use ($inventoryService) {
                // 1. Pessimistic lock on the active receipt record for idempotency
                $receipt = \App\Models\StockInReceipt::where('id', $this->activeReceipt->id)
                    ->lockForUpdate()
                    ->first();

                if (!$receipt || $receipt->status !== 'ACTIVE') {
                    throw new \Exception("This receipt session has already been committed or is no longer active.");
                }

                // 2. Sort cart items by bin_id ASC to prevent deadlocks (null/unassigned bins go to the end)
                $sortedCart = array_values($this->cart);
                usort($sortedCart, function ($a, $b) {
                    $binA = $a['bin_id'];
                    $binB = $b['bin_id'];
                    if ($binA === $binB) return 0;
                    if ($binA === null) return 1;
                    if ($binB === null) return -1;
                    return $binA <=> $binB;
                });

                // 3. Process stock movements in deterministic order
                foreach ($sortedCart as $item) {
                    $bin = $item['bin_id'] ? Bin::find($item['bin_id']) : null;
                    
                    if ($bin) {
                        // Commit active inventory stock adjustment via InventoryService
                        $inventoryService->moveStock(
                            $bin,
                            $item['qty'],
                            'IN',
                            $this->reference ?: 'Manual Stock IN',
                            auth()->id(), // created_by
                            $item['supplier_id']
                        );
                    } else {
                        // Single Source of Truth: call the unified helper on InventoryService instead of direct write
                        $inventoryService->moveStockWithoutBin(
                            $item['item_variant_id'],
                            $item['qty'],
                            'IN',
                            $this->reference ?: 'Manual Stock IN',
                            auth()->id(),
                            $item['supplier_id']
                        );
                    }
                }

                // 4. Commit Inbound Receipt Draft
                $receipt->update([
                    'status' => 'COMMITTED',
                    'supplier_id' => $this->supplier_id ?: null,
                    'purchase_order_ref' => $this->reference ?: null,
                    'last_activity_at' => now(),
                ]);
            });

            // Cleanup & Start New Session
            $this->cart = [];
            $this->reset(['barcode', 'currentItem', 'qty', 'bin_id', 'binCode', 'supplier_id', 'lastAction', 'reference', 'binAutoAssigned', 'recommendedBinCode']);
            
            $this->createNewReceiptSession();

            $this->dispatch('message-dispatched', message: 'Stock successfully committed to inventory!', type: 'success');
            $this->dispatch('focus-barcode-input');
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

    public function focusInput()
    {
        $this->dispatch('focus-barcode-input');
    }

    public function render()
    {
        return view('livewire.stock.stock-in-page', [
            'bins' => Bin::forActiveWarehouse()->orderBy('code')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }
}
