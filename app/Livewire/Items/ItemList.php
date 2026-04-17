<?php

namespace App\Livewire\Items;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ItemVariant;
use App\Models\Category;

class ItemList extends Component
{
    use WithPagination;

    public $search = '';
    public $brandFilter = '';
    public $sortField = 'created_at';
    public $sortDir = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'brandFilter' => ['except' => ''],
        'sortField' => ['except' => 'created_at'],
        'sortDir' => ['except' => 'desc'],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }
    }

    public function render()
    {
        $query = ItemVariant::with(['item', 'primaryBarcode', 'bins', 'suppliers', 'images']);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->whereHas('item', function ($sq) {
                    $sq->where('name', 'like', '%' . $this->search . '%');
                })
                ->orWhere('erp_code', 'like', '%' . $this->search . '%')
                ->orWhere('sku', 'like', '%' . $this->search . '%')
                ->orWhereHas('barcodes', function ($sq) {
                    $sq->where('barcode', 'like', '%' . $this->search . '%');
                });
            });
        }

        if (!empty($this->brandFilter)) {
            $query->where('brand', $this->brandFilter);
        }

        $query->orderBy($this->sortField, $this->sortDir);

        $variants = $query->paginate(24);

        $brands = ItemVariant::select('brand')->distinct()->whereNotNull('brand')->pluck('brand');

        return view('livewire.items.item-list', [
            'variants' => $variants,
            'brands' => $brands,
        ]);
    }
}
