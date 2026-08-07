<?php

namespace App\Livewire\OutstandingPurchase;

use App\Models\OutstandingPurchaseOrder;
use Livewire\Component;

class OutstandingPurchaseShowPage extends Component
{
    public $orderId;

    public function mount($id)
    {
        $this->orderId = $id;
    }

    public function render()
    {
        $order = OutstandingPurchaseOrder::forActiveWarehouse()
            ->with(['items.variant'])
            ->findOrFail($this->orderId);

        $order->healVariantMappings();
        $order->refresh();

        return view('livewire.outstanding-purchase.outstanding-purchase-show-page', [
            'order' => $order
        ])->layout('layouts.app');
    }
}
