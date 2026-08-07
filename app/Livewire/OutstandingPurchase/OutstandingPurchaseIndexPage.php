<?php

namespace App\Livewire\OutstandingPurchase;

use App\Models\OutstandingPurchaseOrder;
use Livewire\Component;
use Livewire\WithPagination;

class OutstandingPurchaseIndexPage extends Component
{
    use WithPagination;

    public $search = '';
    public $filterStatus = 'all'; // all, pending, partial, closed, archived, ready_to_receive, needs_catalog

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => 'all'],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterStatus()
    {
        $this->resetPage();
    }

    public function render()
    {
        // Dynamically heal unmatched items if variants are created
        $needsHealing = OutstandingPurchaseOrder::forActiveWarehouse()
            ->whereHas('items', function ($q) { $q->whereNull('item_variant_id'); })
            ->get();
        foreach ($needsHealing as $po) {
            $po->healVariantMappings();
        }

        // 1. Base Query with warehouse and search filter
        $baseQuery = OutstandingPurchaseOrder::forActiveWarehouse();

        if ($this->search) {
            $baseQuery->where(function ($q) {
                $q->where('po_number', 'like', '%' . $this->search . '%')
                  ->orWhere('supplier_name_snapshot', 'like', '%' . $this->search . '%')
                  ->orWhere('supplier_code_snapshot', 'like', '%' . $this->search . '%')
                  ->orWhereHas('items', function ($itemQuery) {
                      $itemQuery->where('erp_code', 'like', '%' . $this->search . '%')
                                ->orWhere('item_name_snapshot', 'like', '%' . $this->search . '%');
                  });
            });
        }

        // 2. Tally counts for summary cards
        $counts = [
            'total' => (clone $baseQuery)->where('is_archived', false)->count(),
            'pending' => (clone $baseQuery)->where('status', OutstandingPurchaseOrder::STATUS_PENDING)->where('is_archived', false)->count(),
            'partial' => (clone $baseQuery)->where('status', OutstandingPurchaseOrder::STATUS_PARTIAL)->where('is_archived', false)->count(),
            'closed' => (clone $baseQuery)->where('status', OutstandingPurchaseOrder::STATUS_CLOSED)->where('is_archived', false)->count(),
            'archived' => (clone $baseQuery)->where('is_archived', true)->count(),
            'ready' => (clone $baseQuery)->where('is_archived', false)->whereDoesntHave('items', function ($q) {
                $q->whereNull('item_variant_id');
            })->count(),
            'needs_catalog' => (clone $baseQuery)->where('is_archived', false)->whereHas('items', function ($q) {
                $q->whereNull('item_variant_id');
            })->count(),
        ];

        // 3. Build filtered query for the list
        $query = (clone $baseQuery)->with('items');

        if ($this->filterStatus === 'pending') {
            $query->where('status', OutstandingPurchaseOrder::STATUS_PENDING)->where('is_archived', false);
        } elseif ($this->filterStatus === 'partial') {
            $query->where('status', OutstandingPurchaseOrder::STATUS_PARTIAL)->where('is_archived', false);
        } elseif ($this->filterStatus === 'closed') {
            $query->where('status', OutstandingPurchaseOrder::STATUS_CLOSED)->where('is_archived', false);
        } elseif ($this->filterStatus === 'archived') {
            $query->where('is_archived', true);
        } elseif ($this->filterStatus === 'ready_to_receive') {
            $query->where('is_archived', false)->whereDoesntHave('items', function ($q) {
                $q->whereNull('item_variant_id');
            });
        } elseif ($this->filterStatus === 'needs_catalog') {
            $query->where('is_archived', false)->whereHas('items', function ($q) {
                $q->whereNull('item_variant_id');
            });
        } else {
            // all
            $query->where('is_archived', false);
        }

        // Sort: Pending first, Newest PO Date, PO Number
        $query->orderBy('status', 'asc')
              ->orderBy('po_date', 'desc')
              ->orderBy('po_number', 'asc');

        $orders = $query->paginate(15);

        return view('livewire.outstanding-purchase.outstanding-purchase-index-page', [
            'orders' => $orders,
            'counts' => $counts,
        ])->layout('layouts.app');
    }
}
