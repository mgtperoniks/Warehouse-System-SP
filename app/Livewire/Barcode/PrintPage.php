<?php

namespace App\Livewire\Barcode;

use Livewire\Component;
use App\Models\ItemVariant;

class PrintPage extends Component
{
    public $searchString = '';
    public $selectedVariant = null;
    public $copies = 1;

    public function render()
    {
        $items = collect();
        if (strlen($this->searchString) > 2) {
            $items = ItemVariant::with('item')
                ->where('erp_code', 'like', '%' . $this->searchString . '%')
                ->orWhereHas('item', function($q) {
                    $q->where('name', 'like', '%' . $this->searchString . '%');
                })
                ->take(5)
                ->get();
        }

        return view('livewire.barcode.print-page', [
            'searchResults' => $items
        ])->layout('layouts.app');
    }

    public function selectItem($id)
    {
        $this->selectedVariant = ItemVariant::with(['item', 'primaryBarcode'])->find($id);
        $this->searchString = '';
    }
}
