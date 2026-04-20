<?php

namespace App\Livewire\Items;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ItemVariant;
use App\Models\Category;
use App\Services\Inventory\ItemService;

class ItemList extends Component
{
    use WithPagination;

    public $search = '';
    public $brandFilter = '';
    public $stockStatusFilter = '';
    public $sortField = 'name';
    public $sortDir = 'asc';
    public $perPage = 25;

    protected $queryString = [
        'search' => ['except' => ''],
        'brandFilter' => ['except' => ''],
        'stockStatusFilter' => ['except' => ''],
        'sortField' => ['except' => 'name'],
        'sortDir' => ['except' => 'asc'],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingBrandFilter()
    {
        $this->resetPage();
    }

    public function updatingStockStatusFilter()
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

    public function render(ItemService $itemService)
    {
        $filters = [
            'search' => $this->search,
            'brand'  => $this->brandFilter,
            'status' => $this->stockStatusFilter,
            'sort_field' => $this->sortField,
            'sort_dir' => $this->sortDir,
        ];

        $variants = $itemService->getPaginatedItems($filters, (int)$this->perPage);
        $brands = $itemService->getBrands();

        return view('livewire.items.item-list', [
            'variants' => $variants,
            'brands' => $brands,
        ]);
    }
}
