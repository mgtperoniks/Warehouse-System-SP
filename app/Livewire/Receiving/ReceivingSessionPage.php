<?php

namespace App\Livewire\Receiving;

use App\Models\ReceivingSession;
use App\Models\ReceivingSessionItem;
use App\Models\ReceivingSignature;
use App\Models\OutstandingPurchaseOrder;
use App\Models\OutstandingPurchaseOrderItem;
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

    public function incrementQty($itemId)
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($itemId);
        $item->received_qty += 1;
        $item->save();
    }

    public function decrementQty($itemId)
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($itemId);
        if ($item->received_qty > 0) {
            $item->received_qty -= 1;
            $item->save();
        }
    }

    public function setQtyManual($itemId, $qty)
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($itemId);
        $qtyVal = (int) $qty;
        if ($qtyVal >= 0) {
            $item->received_qty = $qtyVal;
            $item->save();
        }
    }

    public function verifyLine($itemId)
    {
        $session = $this->getSession();
        if ($session->status !== ReceivingSession::STATUS_DRAFT) {
            $this->dispatch('message-dispatched', message: 'Cannot verify line. Session is not in DRAFT status.', type: 'error');
            return;
        }

        $item = ReceivingSessionItem::where('receiving_session_id', $session->id)->findOrFail($itemId);
        $item->verification_status = ReceivingSessionItem::STATUS_VERIFIED;
        // Clear any prior removed values
        $item->removed_reason = null;
        $item->remarks = null;
        $item->save();

        $this->dispatch('message-dispatched', message: 'Line verified successfully.', type: 'success');
    }

    public function openRemoveModal($itemId)
    {
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
     * ── SPRINT REC-02B METHODS ──────────────────────────────────────────
     */

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

        // Validate quantities are valid non-negative integers
        foreach ($session->items as $item) {
            if ($item->received_qty < 0) {
                $this->dispatch('message-dispatched', message: 'Cannot review session: Invalid quantities found.', type: 'error');
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
            $this->dispatch('message-dispatched', message: 'Cannot save signature: Session is finalized.', type: 'error');
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

            $fileName = 'signatures/session_' . $session->id . '_' . strtolower($role) . '_' . time() . '.png';
            Storage::disk('public')->put($fileName, $decoded);

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
     * Transactional and idempotent final WMS commit boundary
     */
    public function finalizeReceiving(InventoryService $inventoryService)
    {
        try {
            // 1. Authoritative DB transaction
            DB::transaction(function () use ($inventoryService) {
                // 2. Lock the Receiving Session for update to prevent concurrent double-finalizations
                $session = ReceivingSession::whereKey($this->sessionId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // 3. Idempotency Check
                if ($session->status !== ReceivingSession::STATUS_REVIEWED) {
                    throw new \Exception("This receiving session is not in REVIEWED status or has already been finalized.");
                }

                // 4. Warehouse isolation check
                $activeWarehouseId = session()->get('active_warehouse_id');
                if ($session->warehouse_id != $activeWarehouseId) {
                    throw new \Exception("Unauthorized warehouse context.");
                }

                // 5. Verify all three signatures are present
                $signaturesCount = ReceivingSignature::where('receiving_session_id', $session->id)->count();
                if ($signaturesCount < 3) {
                    throw new \Exception("All three signatures are required to finalize receiving.");
                }

                // 6. Validate Bins and Over-receiving before modifying anything
                $sessionItems = ReceivingSessionItem::where('receiving_session_id', $session->id)
                    ->with(['outstandingPurchaseOrderItem'])
                    ->get();

                foreach ($sessionItems as $item) {
                    if ($item->isVerified()) {
                        // Check if bin is assigned in active warehouse
                        $hasBin = Bin::forActiveWarehouse()->where('item_variant_id', $item->item_variant_id)->exists();
                        if (!$hasBin) {
                            throw new \Exception("Location (Bin) is required for item [{$item->outstandingPurchaseOrderItem->item_name_snapshot}] in this warehouse. Please map a location in the catalog first.");
                        }

                        // Block over-receiving if no rules permit it
                        if ($item->received_qty > $item->expected_qty) {
                            throw new \Exception("Cannot finalize receiving: Item [{$item->outstandingPurchaseOrderItem->item_name_snapshot}] is over-received (+ " . ($item->received_qty - $item->expected_qty) . ").");
                        }
                    }
                }

                // 7. Load PO and lock it
                $po = OutstandingPurchaseOrder::whereKey($session->outstanding_purchase_order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // 8. Process Stock movements and PO lines updates
                foreach ($sessionItems as $item) {
                    if ($item->isVerified() && $item->received_qty > 0) {
                        // Resolve the first bin mapped to the variant
                        $bin = Bin::forActiveWarehouse()
                            ->where('item_variant_id', $item->item_variant_id)
                            ->first();

                        if ($bin) {
                            $inventoryService->moveStock(
                                $bin,
                                $item->received_qty,
                                'IN',
                                'Receiving PO: ' . $po->po_number,
                                auth()->id(),
                                $po->supplier_id
                            );
                        } else {
                            // Fallback moveStockWithoutBin
                            $inventoryService->moveStockWithoutBin(
                                $item->item_variant_id,
                                $item->received_qty,
                                'IN',
                                'Receiving PO: ' . $po->po_number,
                                auth()->id(),
                                $po->supplier_id
                            );
                        }

                        // Update PO line received quantity
                        $poItem = OutstandingPurchaseOrderItem::whereKey($item->outstanding_purchase_order_item_id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $poItem->received_qty += $item->received_qty;
                        $poItem->save(); // Triggers PO status recalculation event
                    }
                }

                // 9. Mark session completed
                $session->status = ReceivingSession::STATUS_COMPLETED;
                $session->completed_at = now();
                $session->save();
            });

            // 10. POST-COMMIT: Generate PDF side-effect outside the database transaction
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
     * Dynamic PDF Generation using DomPDF
     */
    protected function generatePdfDocument(ReceivingSession $session)
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
        
        // Save using project default storage configuration
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
