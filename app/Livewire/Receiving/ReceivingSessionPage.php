<?php

namespace App\Livewire\Receiving;

use App\Models\ReceivingSession;
use App\Models\ReceivingSessionItem;
use App\Models\ReceivingSignature;
use App\Models\OutstandingPurchaseOrder;
use App\Models\OutstandingPurchaseOrderItem;
use App\Models\StockTransaction;
use App\Models\StockTransactionItem;
use App\Models\ItemVariant;
use App\Models\Bin;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ReceivingSessionPage extends Component
{
    public $sessionId;
    public $showRemoveModal = false;
    public $activeRemoveItemId = null;
    public $removeReason = '';
    public $removeRemarks = '';
    public $sessionRemarks = '';

    // Listeners for signature data received from the canvas frontend
    protected $listeners = [
        'signature-saved' => 'saveSignature',
    ];

    public function mount($id)
    {
        $this->sessionId = $id;
        $session = ReceivingSession::findOrFail($this->sessionId);

        // Warehouse Isolation Check
        $activeWarehouseId = session()->get('active_warehouse_id');
        if ($session->warehouse_id != $activeWarehouseId) {
            abort(403, 'Unauthorized warehouse context.');
        }

        $this->sessionRemarks = $session->remarks ?? '';
    }

    /**
     * Fetch the current session from database.
     */
    protected function getSession()
    {
        $session = ReceivingSession::with(['outstandingPurchaseOrder', 'items.outstandingPurchaseOrderItem', 'items.variant.item'])
            ->findOrFail($this->sessionId);

        // Warehouse Isolation Check (re-checked on action execution)
        $activeWarehouseId = session()->get('active_warehouse_id');
        if ($session->warehouse_id != $activeWarehouseId) {
            abort(403, 'Unauthorized warehouse context.');
        }

        return $session;
    }

    public function incrementQtyDatang($itemId, $step = 1)
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($itemId);
        $current = $item->qty_datang !== null ? (float)$item->qty_datang : (float)$item->received_qty;
        $newQty = round($current + (float)$step, 3);
        $item->qty_datang = $newQty;
        // QTY TERIMA automatically follows QTY DATANG for normal receiving
        $item->received_qty = $newQty;
        $item->save();
    }

    public function decrementQtyDatang($itemId, $step = 1)
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($itemId);
        $current = $item->qty_datang !== null ? (float)$item->qty_datang : (float)$item->received_qty;
        if ($current > 0) {
            $newQty = max(0.0, round($current - (float)$step, 3));
            $item->qty_datang = $newQty;
            // QTY TERIMA automatically follows QTY DATANG for normal receiving
            $item->received_qty = $newQty;
            $item->save();
        }
    }

    public function setQtyDatangManual($itemId, $qty)
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($itemId);
        $qtyVal = (float)$qty;
        if ($qtyVal >= 0) {
            $newQty = round($qtyVal, 3);
            $item->qty_datang = $newQty;
            // QTY TERIMA automatically follows QTY DATANG for normal receiving
            $item->received_qty = $newQty;
            $item->save();
        }
    }

    public function incrementQtyTerima($itemId, $step = 1)
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($itemId);
        $item->received_qty = round((float)$item->received_qty + (float)$step, 3);
        $item->save();
    }

    public function decrementQtyTerima($itemId, $step = 1)
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($itemId);
        if ((float)$item->received_qty > 0) {
            $item->received_qty = max(0.0, round((float)$item->received_qty - (float)$step, 3));
            $item->save();
        }
    }

    public function setQtyTerimaManual($itemId, $qty)
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($itemId);
        $qtyVal = (float)$qty;
        if ($qtyVal >= 0) {
            $item->received_qty = round($qtyVal, 3);
            $item->save();
        }
    }

    // Legacy aliases for backward compatibility
    public function incrementQty($itemId)
    {
        $this->incrementQtyDatang($itemId, 1);
    }

    public function decrementQty($itemId)
    {
        $this->decrementQtyDatang($itemId, 1);
    }

    public function setQtyManual($itemId, $qty)
    {
        $this->setQtyDatangManual($itemId, $qty);
    }

    public function setCheckResult($itemId, $result)
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot modify inspection result. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $validResults = [ReceivingSessionItem::CHECK_OK, ReceivingSessionItem::CHECK_REJECT, ReceivingSessionItem::CHECK_RUSAK];
        if (!in_array($result, $validResults)) {
            $this->dispatch('message-dispatched', message: 'Invalid inspection result selected.', type: 'error');
            return;
        }

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($itemId);
        $item->check_result = $result;
        
        // When switched to OK, ensure received_qty matches qty_datang
        if ($result === ReceivingSessionItem::CHECK_OK) {
            $datang = $item->qty_datang !== null ? (float)$item->qty_datang : (float)$item->expected_qty;
            $item->received_qty = $datang;
        }

        $item->save();
    }

    public function setCheckNotes($itemId, $notes)
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            return;
        }

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($itemId);
        $item->check_notes = trim($notes) ?: null;
        $item->save();
    }

    public function verifyLine($itemId)
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot verify line. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($itemId);

        // Auto-populate qty_datang if not explicitly set yet
        if ($item->qty_datang === null) {
            $item->qty_datang = (float)$item->received_qty > 0 ? (float)$item->received_qty : (float)$item->expected_qty;
        }

        // Auto-sync received_qty if it was 0 and result is OK
        if ((float)$item->received_qty === 0.0 && (float)$item->qty_datang > 0 && ($item->check_result === ReceivingSessionItem::CHECK_OK || empty($item->check_result))) {
            $item->received_qty = $item->qty_datang;
        }

        // Auto-populate check_result to OK if empty
        if (empty($item->check_result)) {
            $item->check_result = ReceivingSessionItem::CHECK_OK;
        }

        // Validate quantities are non-negative
        if ((float)$item->qty_datang < 0 || (float)$item->received_qty < 0) {
            $this->dispatch('message-dispatched', message: 'Quantities cannot be negative.', type: 'error');
            return;
        }

        // Validate inspection result is in valid list
        if (!in_array($item->check_result, ReceivingSessionItem::CHECK_RESULTS)) {
            $this->dispatch('message-dispatched', message: 'Invalid inspection result selected.', type: 'error');
            return;
        }

        $item->verification_status = ReceivingSessionItem::STATUS_VERIFIED;
        // Clear any prior removed values
        $item->removed_reason = null;
        $item->remarks = null;
        $item->save();

        $this->dispatch('message-dispatched', message: 'Line verified successfully.', type: 'success');
    }

    public function unverifyLine($itemId)
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot edit line. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($itemId);
        $item->verification_status = ReceivingSessionItem::STATUS_PENDING;
        $item->save();

        $this->dispatch('message-dispatched', message: 'Item returned to pending for editing.', type: 'success');
    }

    public function openRemoveModal($itemId)
    {
        $session = $this->getSession();
        if (!$session->isDraft()) {
            $this->dispatch('message-dispatched', message: 'Cannot remove line. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $this->activeRemoveItemId = $itemId;
        $this->removeReason = 'WRONG WAREHOUSE';
        $this->removeRemarks = '';
        $this->showRemoveModal = true;
    }

    public function closeRemoveModal()
    {
        $this->showRemoveModal = false;
        $this->activeRemoveItemId = null;
        $this->removeReason = '';
        $this->removeRemarks = '';
    }

    public function removeLine()
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot remove line. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $this->validate([
            'removeReason' => 'required|in:WRONG WAREHOUSE,IMPORTED BY MISTAKE,CANCELLED,OTHER',
            'removeRemarks' => 'required_if:removeReason,OTHER',
        ], [
            'removeRemarks.required_if' => 'Please provide a short explanation for the OTHER reason.',
        ]);

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($this->activeRemoveItemId);
        $item->verification_status = ReceivingSessionItem::STATUS_REMOVED;
        $item->removed_reason = $this->removeReason;
        $item->remarks = $this->removeRemarks ?: null;
        $item->save();

        $this->closeRemoveModal();
        $this->dispatch('message-dispatched', message: 'Line marked as REMOVED.', type: 'success');
    }

    public function saveDraft()
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot save draft. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $session->remarks = $this->sessionRemarks;
        $session->save();

        $this->dispatch('message-dispatched', message: 'Draft session saved successfully.', type: 'success');
    }

    public function completeChecking()
    {
        $this->completeVerification();
    }

    public function completeVerification()
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot complete verification. Session is not in DRAFT status.', type: 'error');
            return;
        }

        // Check completion rules
        if ($session->pendingLines > 0) {
            $this->dispatch('message-dispatched', message: 'Cannot complete verification: There are still pending lines.', type: 'error');
            return;
        }

        if ($session->verifiedLines === 0) {
            $this->dispatch('message-dispatched', message: 'Cannot complete verification: At least one line must be VERIFIED.', type: 'error');
            return;
        }

        // Save session remarks if any
        $session->remarks = $this->sessionRemarks;
        $session->status = ReceivingSession::STATUS_READY_REVIEW;
        $session->save();

        $this->dispatch('message-dispatched', message: 'Verification complete. Ready for final review.', type: 'success');
    }

    /**
     * transition: READY_REVIEW -> REVIEWED
     */
    public function reviewAndConfirm()
    {
        $session = $this->getSession();
        
        if ($session->status !== ReceivingSession::STATUS_READY_REVIEW) {
            $this->dispatch('message-dispatched', message: 'Cannot review session: Status is not READY_REVIEW.', type: 'error');
            return;
        }

        // Validate no pending lines remain
        if ($session->pendingLines > 0) {
            $this->dispatch('message-dispatched', message: 'Cannot review session: There are still pending lines.', type: 'error');
            return;
        }

        // Validate at least one verified line
        if ($session->verifiedLines === 0) {
            $this->dispatch('message-dispatched', message: 'Cannot review session: At least one line must be VERIFIED.', type: 'error');
            return;
        }

        // Validate quantities are valid non-negative numbers
        foreach ($session->items as $item) {
            if ((float)$item->received_qty < 0 || (float)$item->qty_datang < 0) {
                $this->dispatch('message-dispatched', message: 'Cannot review session: Invalid negative quantities found.', type: 'error');
                return;
            }
        }

        $session->reviewed_by = auth()->id();
        $session->reviewed_at = now();
        $session->status = ReceivingSession::STATUS_REVIEWED;
        $session->save();

        $this->dispatch('message-dispatched', message: 'Session reviewed and confirmed. Ready for digital signatures.', type: 'success');
    }

    /**
     * Save digital signature image from canvas
     */
    public function saveSignature($role, $signatureData)
    {
        $session = $this->getSession();

        if ($session->status === ReceivingSession::STATUS_COMPLETED || $session->status === ReceivingSession::STATUS_CANCELLED) {
            $this->dispatch('message-dispatched', message: 'Cannot save signature: Session is finalized and immutable.', type: 'error');
            return;
        }

        if ($session->status !== ReceivingSession::STATUS_REVIEWED) {
            $this->dispatch('message-dispatched', message: 'Cannot save signature: Session must be in REVIEWED status.', type: 'error');
            return;
        }

        // Check if signature already exists for role (controlled replacement flow)
        $existing = ReceivingSignature::where('receiving_session_id', $session->id)
            ->where('role', $role)
            ->first();

        if ($existing) {
            $this->dispatch('message-dispatched', message: 'Signature already exists for this role. Clear it first to re-sign.', type: 'error');
            return;
        }

        // Process base64 PNG data
        try {
            $data = preg_replace('#^data:image/\w+;base64,#i', '', $signatureData);
            $decoded = base64_decode($data);
            if (!$decoded) {
                throw new \Exception("Invalid base64 payload.");
            }

            // Auto-trim transparent whitespace margins around handwriting
            $trimmed = \App\Http\Controllers\Receiving\ReceivingPdfController::trimSignaturePng($decoded);

            $fileName = 'signatures/session_' . $session->id . '_' . strtolower($role) . '_' . time() . '.png';
            Storage::disk('public')->put($fileName, $trimmed);

            ReceivingSignature::create([
                'receiving_session_id' => $session->id,
                'role' => $role,
                'signature_path' => $fileName,
                'signed_by' => auth()->id(),
                'signed_at' => now(),
            ]);

            $this->dispatch('message-dispatched', message: 'Signature saved successfully.', type: 'success');

        } catch (\Exception $e) {
            $this->dispatch('message-dispatched', message: 'Error saving signature: ' . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Clear signature to allow re-signing before finalization
     */
    public function clearSignature($role)
    {
        $session = $this->getSession();

        if ($session->status === ReceivingSession::STATUS_COMPLETED || $session->status === ReceivingSession::STATUS_CANCELLED) {
            $this->dispatch('message-dispatched', message: 'Cannot clear signature: Session is finalized.', type: 'error');
            return;
        }

        $sig = ReceivingSignature::where('receiving_session_id', $session->id)
            ->where('role', $role)
            ->first();

        if ($sig) {
            // Delete file from disk
            if (Storage::disk('public')->exists($sig->signature_path)) {
                Storage::disk('public')->delete($sig->signature_path);
            }
            $sig->delete();

            $this->dispatch('message-dispatched', message: 'Signature cleared successfully.', type: 'success');
        }
    }

    /**
     * Atomic, pessimistic-locked, and idempotent final WMS commit boundary
     */
    public function finalizeReceiving(InventoryService $inventoryService)
    {
        try {
            // 1. Authoritative DB transaction with pessimistic locking
            DB::transaction(function () use ($inventoryService) {
                // 2. Lock the Receiving Session for update to prevent concurrent double-finalizations
                $session = ReceivingSession::whereKey($this->sessionId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // 3. Idempotency Check
                if ($session->status === ReceivingSession::STATUS_COMPLETED || $session->status !== ReceivingSession::STATUS_REVIEWED) {
                    throw new \Exception("This receiving session is not in REVIEWED status or has already been finalized.");
                }

                // 4. Warehouse isolation check
                $activeWarehouseId = session()->get('active_warehouse_id');
                if ($session->warehouse_id != $activeWarehouseId) {
                    throw new \Exception("Unauthorized warehouse context.");
                }

                // 5. Load and lock PO
                $po = OutstandingPurchaseOrder::whereKey($session->outstanding_purchase_order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // 6. Lock PO line items
                $poItems = OutstandingPurchaseOrderItem::where('outstanding_purchase_order_id', $po->id)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $sessionItems = ReceivingSessionItem::where('receiving_session_id', $session->id)
                    ->lockForUpdate()
                    ->get();

                // 7. Lock affected variants & bins in ascending order to prevent deadlocks
                $variantIds = $sessionItems->where('verification_status', ReceivingSessionItem::STATUS_VERIFIED)
                    ->pluck('item_variant_id')
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();

                if ($variantIds->isNotEmpty()) {
                    ItemVariant::whereIn('id', $variantIds)->lockForUpdate()->get();
                    Bin::forActiveWarehouse()->whereIn('item_variant_id', $variantIds)->orderBy('id')->lockForUpdate()->get();
                }

                // 8. Validate Bins mapping before modifying anything
                foreach ($sessionItems as $item) {
                    if ($item->isVerified()) {
                        $hasBin = Bin::forActiveWarehouse()->where('item_variant_id', $item->item_variant_id)->exists();
                        if (!$hasBin) {
                            $poLine = $poItems->get($item->outstanding_purchase_order_item_id);
                            $itemName = $poLine ? $poLine->item_name_snapshot : 'Item #' . $item->id;
                            throw new \Exception("Location (Bin) is required for item [{$itemName}] in this warehouse. Please map a location in the catalog first.");
                        }
                    }
                }

                // 9. Create exactly ONE StockTransaction (IN) for this session
                $date = now()->format('Y-m-d');
                $prefix = 'IN-' . $date . '-';
                $lastTx = StockTransaction::where('code', 'like', $prefix . '%')->orderBy('code', 'desc')->first();
                $sequence = $lastTx ? ((int) substr($lastTx->code, -4)) + 1 : 1;
                $txCode = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);

                $stockTx = StockTransaction::create([
                    'warehouse_id' => $session->warehouse_id,
                    'code' => $txCode,
                    'type' => 'IN',
                    'status' => 'CONFIRMED',
                    'reference' => 'RECEIVING_SESSION:' . $session->id,
                    'user_id' => auth()->id(),
                    'operator_id' => auth()->id(),
                    'terminal_id' => session()->get('wms_terminal_id') ?: 'SPAREPART-DESK-A',
                    'terminal_session_id' => session()->getId(),
                ]);

                // 10. Process Stock movements and PO lines updates for VERIFIED items
                foreach ($sessionItems as $item) {
                    if ($item->isVerified() && (float)$item->received_qty > 0) {
                        $poItem = $poItems->get($item->outstanding_purchase_order_item_id);

                        // Resolve the first bin mapped to the variant
                        $bin = Bin::forActiveWarehouse()
                            ->where('item_variant_id', $item->item_variant_id)
                            ->first();

                        if ($bin) {
                            $inventoryService->moveStock(
                                $bin,
                                (float)$item->received_qty,
                                'IN',
                                'Receiving PO: ' . $po->po_number,
                                auth()->id(),
                                $po->supplier_id
                            );
                        } else {
                            $inventoryService->moveStockWithoutBin(
                                $item->item_variant_id,
                                (float)$item->received_qty,
                                'IN',
                                'Receiving PO: ' . $po->po_number,
                                auth()->id(),
                                $po->supplier_id
                            );
                        }

                        // Create StockTransactionItem ledger
                        StockTransactionItem::create([
                            'stock_transaction_id' => $stockTx->id,
                            'item_variant_id' => $item->item_variant_id,
                            'bin_id' => $bin ? $bin->id : null,
                            'qty' => (float)$item->received_qty,
                            'item_name_snapshot' => $poItem ? $poItem->item_name_snapshot : '',
                            'erp_code_snapshot' => $poItem ? $poItem->erp_code : '',
                            'unit_snapshot' => $poItem ? $poItem->unit : 'PCS',
                            'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED,
                        ]);

                        // Increment PO line received quantity
                        if ($poItem) {
                            $poItem->received_qty = round((float)$poItem->received_qty + (float)$item->received_qty, 3);
                            $poItem->save();
                        }
                    }
                }

                // 11. Recalculate PO status
                $po->recalculateStatus();
                $po->save();

                // 12. Mark session completed
                $session->status = ReceivingSession::STATUS_COMPLETED;
                $session->completed_by = auth()->id();
                $session->completed_at = now();
                $session->save();
            });

            // 13. POST-COMMIT: Generate ISO PDF document
            $session = $this->getSession();
            try {
                $this->generatePdfDocument($session);
            } catch (\Exception $pdfEx) {
                \Illuminate\Support\Facades\Log::error("Receiving PDF generation failed: " . $pdfEx->getMessage());
                $this->dispatch('message-dispatched', message: 'Commit successful, but PDF generation failed. The document can be regenerated later.', type: 'error');
                return;
            }

            $this->dispatch('message-dispatched', message: 'Receiving successfully committed and finalized!', type: 'success');

        } catch (\Exception $e) {
            $this->dispatch('message-dispatched', message: 'Finalization failed: ' . $e->getMessage(), type: 'error');
        }
    }

    /**
     * Dynamic ISO PDF Generation using DomPDF
     */
    public function generatePdfDocument(ReceivingSession $session)
    {
        $items = $session->items()->with(['outstandingPurchaseOrderItem', 'variant.item'])->get();
        $signatures = ReceivingSignature::where('receiving_session_id', $session->id)->get();

        $sigMap = [];
        foreach ($signatures as $sig) {
            $sigMap[$sig->role] = $sig;
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.receiving-inspection-pdf', [
            'session' => $session,
            'items' => $items,
            'signatures' => $sigMap,
        ])->setPaper('a4', 'portrait');

        $pdfPath = 'receiving/receiving_session_' . $session->id . '.pdf';
        
        // Save using project storage configuration
        Storage::disk('public')->put($pdfPath, $pdf->output());

        $session->pdf_path = $pdfPath;
        $session->save();
    }

    public function render()
    {
        $session = $this->getSession();

        return view('livewire.receiving.receiving-session-page', [
            'session' => $session,
            'items' => $session->items()->with(['outstandingPurchaseOrderItem', 'variant.item'])->get(),
            'diserahkanSig' => ReceivingSignature::where('receiving_session_id', $session->id)->where('role', 'DISERAHKAN_OLEH')->first(),
            'diterimaSig' => ReceivingSignature::where('receiving_session_id', $session->id)->where('role', 'DITERIMA_OLEH')->first(),
            'gudangSig' => ReceivingSignature::where('receiving_session_id', $session->id)->where('role', 'BAG_GUDANG')->first(),
        ])->layout('layouts.app');
    }
}
