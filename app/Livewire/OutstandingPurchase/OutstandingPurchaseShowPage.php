<?php

namespace App\Livewire\OutstandingPurchase;

use App\Models\OutstandingPurchaseOrder;
use App\Models\ReceivingSession;
use App\Models\ReceivingSessionItem;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class OutstandingPurchaseShowPage extends Component
{
    public $orderId;

    public function mount($id)
    {
        $this->orderId = $id;
    }

    /**
     * Authoritatively creates or resumes a receiving session for this purchase order.
     *
     * Business rules:
     * - REVIEWED session: redirect only, never create a new one
     * - DRAFT / READY_REVIEW session: resume by redirecting
     * - Fully received WMS PO (pending = 0): block new session creation
     * - New session is only created when truly no active session exists and pending qty > 0
     */
    public function startReceivingSession()
    {
        $poId = $this->orderId;

        try {
            $session = DB::transaction(function () use ($poId) {
                // 1. Lock the PO for update to prevent concurrent creation
                $po = OutstandingPurchaseOrder::forActiveWarehouse()
                    ->whereKey($poId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // 2. Authoritative duplicate check — includes ALL non-completed active states
                //    REVIEWED must never result in a new session being created.
                $activeSession = ReceivingSession::where('outstanding_purchase_order_id', $po->id)
                    ->whereIn('status', [
                        ReceivingSession::STATUS_DRAFT,
                        ReceivingSession::STATUS_READY_REVIEW,
                        ReceivingSession::STATUS_REVIEWED,
                    ])
                    ->first();

                if ($activeSession) {
                    // Always redirect to the existing active session, regardless of its state.
                    return $activeSession;
                }

                // 3. Eligibility Checks — only reached when no active session exists
                if ($po->is_archived) {
                    throw new \Exception("Cannot start receiving session: Purchase order is archived.");
                }

                if ($po->receiving_readiness !== OutstandingPurchaseOrder::READINESS_READY) {
                    throw new \Exception("Cannot start receiving session: Item catalog mapping is incomplete.");
                }

                $itemsCount = $po->items()->count();
                if ($itemsCount === 0) {
                    throw new \Exception("Cannot start receiving session: Purchase order has no line items.");
                }

                // 4. WMS Fully Received Guard — never reopen a fully received PO
                //    ERP_BEHIND is not a reason to reopen. The WMS pending_qty is authoritative.
                $totalPending = $po->items()->get()->sum('pending_qty');
                if ($totalPending <= 0.0001) {
                    throw new \Exception("Cannot start receiving session: All items have already been received in WMS. Check ERP sync status for reconciliation details.");
                }

                // 5. Create Receiving Session
                $newSession = ReceivingSession::create([
                    'warehouse_id' => $po->warehouse_id,
                    'outstanding_purchase_order_id' => $po->id,
                    'status' => ReceivingSession::STATUS_DRAFT,
                    'created_by' => auth()->id(),
                    'started_at' => now(),
                ]);

                // 6. Snapshot PO lines — only lines with remaining pending quantity
                $poItems = $po->items()->get()->filter(function ($item) {
                    return (float)$item->pending_qty > 0.0001;
                });

                if ($poItems->isEmpty()) {
                    // Safety fallback — should never reach here due to check above
                    $poItems = $po->items()->get();
                }

                foreach ($poItems as $poItem) {
                    ReceivingSessionItem::create([
                        'receiving_session_id' => $newSession->id,
                        'outstanding_purchase_order_item_id' => $poItem->id,
                        'item_variant_id' => $poItem->item_variant_id,
                        'expected_qty' => $poItem->pending_qty > 0 ? $poItem->pending_qty : $poItem->ordered_qty,
                        'received_qty' => 0.0,
                        'qty_datang' => null,
                        'verification_status' => ReceivingSessionItem::STATUS_PENDING,
                    ]);
                }

                // 7. Link the session reference on PO (as a convenience)
                $po->receiving_session_id = $newSession->id;
                $po->save();

                return $newSession;
            });

            // Redirect to receiving session page
            return redirect()->route('receiving.session', ['id' => $session->id]);

        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            return;
        }
    }

    public function render()
    {
        $order = OutstandingPurchaseOrder::forActiveWarehouse()
            ->with(['items.variant'])
            ->findOrFail($this->orderId);

        // Heal variant mappings on demand (show page only — not on index page)
        $order->healVariantMappings();
        $order->refresh();

        // Active session: any non-completed, non-cancelled session
        $activeSession = ReceivingSession::where('outstanding_purchase_order_id', $order->id)
            ->whereIn('status', [
                ReceivingSession::STATUS_DRAFT,
                ReceivingSession::STATUS_READY_REVIEW,
                ReceivingSession::STATUS_REVIEWED,
            ])
            ->first();

        // Completed sessions history
        $completedSessions = ReceivingSession::where('outstanding_purchase_order_id', $order->id)
            ->where('status', ReceivingSession::STATUS_COMPLETED)
            ->with(['items.variant.item', 'creator', 'completedBy'])
            ->latest('completed_at')
            ->get();

        // --- WMS-derived action state (not from ERP) ---
        // This is the primary operator decision: what can the user do right now?
        $wmsItems = $order->items;
        $wmsTotalPending = $wmsItems->sum('pending_qty');
        $wmsTotalReceived = $wmsItems->sum('received_qty');
        $hasCompletedSession = $completedSessions->isNotEmpty();
        $isFullyReceivedInWms = $wmsTotalPending <= 0.0001 && ($hasCompletedSession || $wmsTotalReceived > 0.0001);

        // Derive the action the operator should take
        // Priority: active session state > WMS receipt state > PO readiness
        if ($activeSession) {
            if ($activeSession->status === ReceivingSession::STATUS_REVIEWED) {
                // REVIEWED: always redirect to finalize — regardless of WMS pending qty
                $operatorAction = 'VIEW_FINALIZE';
            } elseif ($isFullyReceivedInWms
                && in_array($activeSession->status, [ReceivingSession::STATUS_DRAFT, ReceivingSession::STATUS_READY_REVIEW])
            ) {
                // Orphaned pre-fix DRAFT/READY_REVIEW session on an already-fully-received PO.
                // The operator should be warned and directed to view the stale session.
                $operatorAction = 'STALE_DRAFT_FULLY_RECEIVED';
            } else {
                $operatorAction = match ($activeSession->status) {
                    ReceivingSession::STATUS_DRAFT => 'RESUME_DRAFT',
                    ReceivingSession::STATUS_READY_REVIEW => 'RESUME_REVIEW',
                    default => 'VIEW_FINALIZE',
                };
            }
        } elseif ($isFullyReceivedInWms) {
            $operatorAction = 'FULLY_RECEIVED';
        } elseif ($wmsTotalPending > 0.0001 && $wmsTotalReceived > 0.0001) {
            $operatorAction = 'PARTIAL_RECEIVED';
        } else {
            $operatorAction = 'READY_TO_RECEIVE';
        }

        return view('livewire.outstanding-purchase.outstanding-purchase-show-page', [
            'order' => $order,
            'activeSession' => $activeSession,
            'completedSessions' => $completedSessions,
            'operatorAction' => $operatorAction,
            'wmsTotalPending' => round((float)$wmsTotalPending, 3),
            'wmsTotalReceived' => round((float)$wmsTotalReceived, 3),
            'isFullyReceivedInWms' => $isFullyReceivedInWms,
        ])->layout('layouts.app');
    }
}

