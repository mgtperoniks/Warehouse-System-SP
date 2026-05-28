<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ItemVariant;
use App\Services\Reports\MovementLedgerService;
use Carbon\Carbon;

class MovementLedgerReport extends Component
{
    use WithPagination;

    // Filters
    public $startDate;
    public $endDate;
    public $movementType = 'ALL';

    // Autocomplete Search State
    public $searchItem = '';
    public $selectedVariantId = null;
    public $selectedVariant = null;

    public bool $reportGenerated = false;

    protected $queryString = [
        'startDate' => ['except' => ''],
        'endDate' => ['except' => ''],
        'movementType' => ['except' => 'ALL'],
        'selectedVariantId' => ['except' => null],
        'reportGenerated' => ['except' => false],
    ];

    public function mount()
    {
        // Default range to current month
        $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::today()->format('Y-m-d');

        // Hydrate from query string if present
        if ($this->selectedVariantId) {
            $this->selectedVariant = ItemVariant::with('item')->find($this->selectedVariantId);
            if ($this->selectedVariant) {
                $this->searchItem = $this->selectedVariant->erp_code . ' - ' . $this->selectedVariant->item->name;
            } else {
                $this->selectedVariantId = null;
            }
        }
    }

    public function updatedStartDate()
    {
        $this->reportGenerated = false;
        $this->resetPage();
    }

    public function updatedEndDate()
    {
        $this->reportGenerated = false;
        $this->resetPage();
    }

    public function updatedMovementType()
    {
        $this->reportGenerated = false;
        $this->resetPage();
    }

    /**
     * Compute search suggestions.
     */
    public function getSuggestionsProperty()
    {
        $search = trim($this->searchItem);
        if (strlen($search) < 2 || ($this->selectedVariant && $search === ($this->selectedVariant->erp_code . ' - ' . $this->selectedVariant->item->name))) {
            return [];
        }

        return ItemVariant::with('item')
            ->where(function($query) use ($search) {
                $query->where('erp_code', 'like', "%{$search}%")
                      ->orWhereHas('item', function($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
            })
            ->limit(8)
            ->get();
    }

    /**
     * Select item variant from suggestions list.
     */
    public function selectItem($variantId)
    {
        $this->selectedVariantId = $variantId;
        $this->selectedVariant = ItemVariant::with('item')->findOrFail($variantId);
        $this->searchItem = $this->selectedVariant->erp_code . ' - ' . $this->selectedVariant->item->name;
        $this->reportGenerated = false;
        $this->resetPage();
    }

    /**
     * Clear selected item variant.
     */
    public function resetItem()
    {
        $this->selectedVariantId = null;
        $this->selectedVariant = null;
        $this->searchItem = '';
        $this->reportGenerated = false;
        $this->resetPage();
    }

    /**
     * Apply preset date intervals.
     */
    public function setDatePreset($preset)
    {
        switch ($preset) {
            case 'today':
                $this->startDate = Carbon::today()->format('Y-m-d');
                $this->endDate = Carbon::today()->format('Y-m-d');
                break;
            case 'this_week':
                $this->startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
                $this->endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
                break;
            case 'this_month':
                $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
                $this->endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
                break;
            case 'this_year':
                $this->startDate = Carbon::now()->startOfYear()->format('Y-m-d');
                $this->endDate = Carbon::now()->endOfYear()->format('Y-m-d');
                break;
        }
        $this->reportGenerated = false;
        $this->resetPage();
    }

    /**
     * Generate Stock Ledger Report.
     */
    public function generateReport()
    {
        if (!$this->selectedVariantId) {
            session()->flash('warning', 'Silakan pilih barang terlebih dahulu.');
            return;
        }

        if (empty($this->startDate) || empty($this->endDate)) {
            session()->flash('warning', 'Silakan pilih Tanggal Mulai dan Tanggal Akhir.');
            return;
        }

        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);

        if ($start->gt($end)) {
            session()->flash('warning', 'Tanggal Mulai tidak boleh lebih besar dari Tanggal Akhir.');
            return;
        }

        if ($start->diffInYears($end) >= 1) {
            session()->flash('warning', 'Rentang tanggal maksimal untuk Kartu Stok adalah 1 tahun.');
            return;
        }

        $this->reportGenerated = true;
        $this->resetPage();
    }

    public function render(MovementLedgerService $ledgerService)
    {
        $movements = collect();
        $startingBalance = 0;
        $totalRows = 0;

        if ($this->reportGenerated && $this->selectedVariantId) {
            $movements = $ledgerService->getPaginatedLedger(
                $this->selectedVariantId,
                $this->startDate,
                $this->endDate,
                $this->movementType,
                100
            );
            $startingBalance = $ledgerService->getStartingBalance($this->selectedVariantId, $this->startDate);
            $totalRows = $movements->total();
        }

        return view('livewire.reports.movement-ledger-report', [
            'movements' => $movements,
            'startingBalance' => $startingBalance,
            'totalRows' => $totalRows,
            'suggestions' => $this->suggestions,
        ])->layout('layouts.app');
    }
}
