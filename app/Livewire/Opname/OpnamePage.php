<?php

namespace App\Livewire\Opname;

use Livewire\Component;

class OpnamePage extends Component
{
    public $binScan = '';
    public $actualQty = 0;

    public function render()
    {
        return view('livewire.opname.opname-page')->layout('layouts.app');
    }

    public function incrementQty()
    {
        $this->actualQty++;
    }

    public function decrementQty()
    {
        if ($this->actualQty > 0) {
            $this->actualQty--;
        }
    }

    public function saveItem()
    {
        // Placeholder for saving logic
    }
}
