<?php

namespace App\Livewire\Items;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Jobs\ImportItemsJob;

class ImportModal extends Component
{
    use WithFileUploads;

    public $isOpen = false;
    public $file = null;
    public $status = '';

    protected $listeners = ['openImportModal' => 'openModal'];

    public function openModal()
    {
        $this->isOpen = true;
        $this->reset(['file', 'status']);
    }

    public function closeModal()
    {
        $this->isOpen = false;
    }

    public function startImport()
    {
        $this->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB CSV
        ]);

        $path = $this->file->store('imports', 'public');
        
        // Dispatch the job
        ImportItemsJob::dispatch($path, auth()->id());
        
        $this->status = 'Import has been queued. You will be notified when it completes.';
        $this->file = null;

        $this->dispatch('importQueued');
    }

    public function render()
    {
        return view('livewire.items.import-modal');
    }
}
