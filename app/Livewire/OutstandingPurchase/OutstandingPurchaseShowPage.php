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

                // 2. Authoritative duplicate check
                $activeSession = ReceivingSession::where('outstanding_purchase_order_id', $po->id)
                    ->whereIn('status', [
                        ReceivingSession::STATUS_DRAFT,
                        ReceivingSession::STATUS_READY_REVIEW
                    ])
                    ->first();

                if ($activeSession) {
                    return $activeSession;
                }

                // 3. Eligibility Checks
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

                // 4. Create Receiving Session
                $newSession = ReceivingSession::create([
                    'warehouse_id' => $po->warehouse_id,
                    'outstanding_purchase_order_id' => $po->id,
                    'status' => ReceivingSession::STATUS_DRAFT,
                    'created_by' => auth()->id(),
                    'started_at' => now(),
                ]);

                // 5. Snapshot PO lines
                $poItems = $po->items()->get();
                foreach ($poItems as $poItem) {
                    ReceivingSessionItem::create([
                        'receiving_session_id' => $newSession->id,
                        'outstanding_purchase_order_item_id' => $poItem->id,
                        'item_variant_id' => $poItem->item_variant_id,
                        'expected_qty' => $poItem->ordered_qty, // Snapshot ordered_qty
                        'received_qty' => 0,
                        'verification_status' => ReceivingSessionItem::STATUS_PENDING,
                    ]);
                }

                // 6. Link the session reference on PO (as a convenience)
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

        $order->healVariantMappings();
        $order->refresh();

        $activeSession = ReceivingSession::where('outstanding_purchase_order_id', $order->id)
            ->whereIn('status', [
                ReceivingSession::STATUS_DRAFT,
                ReceivingSession::STATUS_READY_REVIEW
            ])
            ->first();

        return view('livewire.outstanding-purchase.outstanding-purchase-show-page', [
            'order' => $order,
            'activeSession' => $activeSession,
        ])->layout('layouts.app');
    }
}
