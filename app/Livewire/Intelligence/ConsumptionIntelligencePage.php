<?php

namespace App\Livewire\Intelligence;

use App\Models\ConsumptionSnapshot;
use App\Models\Department;
use App\Models\ItemVariant;
use App\Services\Intelligence\ConsumptionCalculationService;
use Livewire\Component;
use Livewire\WithPagination;

class ConsumptionIntelligencePage extends Component
{
    use WithPagination;

    public string $rankingTab = 'TOP_RISING'; // TOP_RISING, TOP_ACCELERATING, TOP_VOLATILE, TOP_CONSUMPTION, ALL_ACTIVE
    public string $search = '';
    public string $filterClassification = '';
    public string $filterDepartment = '';
    public int $perPage = 10; // Default Top 10

    // Drill down state
    public ?int $selectedVariantId = null;
    public ?ConsumptionSnapshot $selectedSnapshot = null;
    public array $drillDownAttribution = [];
    public array $drillDownSeries = [];
    public bool $showDetailModal = false;

    // Refresh state
    public bool $isCalculating = false;

    protected $queryString = [
        'rankingTab'            => ['except' => 'TOP_RISING'],
        'search'                => ['except' => ''],
        'filterClassification' => ['except' => ''],
        'filterDepartment'     => ['except' => ''],
        'perPage'               => ['except' => 10],
        'page'                  => ['except' => 1],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingRankingTab()
    {
        $this->resetPage();
    }

    public function updatingFilterClassification()
    {
        $this->resetPage();
    }

    public function updatingFilterDepartment()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function setTab(string $tab)
    {
        $this->rankingTab = $tab;
        $this->filterClassification = '';
        $this->resetPage();
    }

    public function setClassificationFilter(string $class)
    {
        $this->filterClassification = ($this->filterClassification === $class) ? '' : $class;
        $this->resetPage();
    }

    public function recalculate(ConsumptionCalculationService $service)
    {
        $warehouseId = session('active_warehouse_id');
        $result = $service->calculateAndPersistSnapshots($warehouseId);

        if (($result['status'] ?? '') === 'success') {
            session()->flash('success', 'Consumption Intelligence snapshots successfully updated.');
        } else {
            session()->flash('error', $result['message'] ?? 'Failed to calculate snapshots.');
        }

        $this->resetPage();
    }

    public function openDetail(int $variantId)
    {
        $this->selectedVariantId = $variantId;
        $this->selectedSnapshot = ConsumptionSnapshot::with(['variant.item'])
            ->where('item_variant_id', $variantId)
            ->first();

        if ($this->selectedSnapshot) {
            $this->drillDownSeries = $this->selectedSnapshot->weekly_series_json ?? [];
            $this->drillDownAttribution = $this->selectedSnapshot->attribution_json ?? [];
            $this->showDetailModal = true;
        }
    }

    public function closeDetail()
    {
        $this->showDetailModal = false;
        $this->selectedVariantId = null;
        $this->selectedSnapshot = null;
        $this->drillDownSeries = [];
        $this->drillDownAttribution = [];
    }

    public function render(ConsumptionCalculationService $service)
    {
        // 1. Check if snapshot exists. If not, calculate once automatically
        $snapshotCount = ConsumptionSnapshot::count();
        if ($snapshotCount === 0) {
            $service->calculateAndPersistSnapshots(session('active_warehouse_id'));
        }

        // 2. Fetch Period info & KPI counts
        $latestPeriod = ConsumptionSnapshot::select('period_start', 'period_end', 'calculated_at')
            ->orderBy('calculated_at', 'desc')
            ->first();

        $totalMasterSkus = ItemVariant::count();

        $kpis = [
            'strong_rising' => ConsumptionSnapshot::where('classification', ConsumptionCalculationService::CLASS_STRONG_RISING)->count(),
            'rising'        => ConsumptionSnapshot::where('classification', ConsumptionCalculationService::CLASS_RISING)->count(),
            'watch'         => ConsumptionSnapshot::where('classification', ConsumptionCalculationService::CLASS_WATCH)->count(),
            'volatile'      => ConsumptionSnapshot::where('classification', ConsumptionCalculationService::CLASS_HIGH_VOLATILITY)->count(),
            'sporadic'      => ConsumptionSnapshot::where('classification', ConsumptionCalculationService::CLASS_SPORADIC_SPIKE)->count(),
            'stable'        => ConsumptionSnapshot::where('classification', ConsumptionCalculationService::CLASS_STABLE)->count(),
            'dormant'       => ConsumptionSnapshot::where('classification', ConsumptionCalculationService::CLASS_DORMANT)->count(),
            'active_total'  => ConsumptionSnapshot::where('active_weeks_count', '>', 0)->count(),
            'total_master'  => $totalMasterSkus,
        ];

        // 3. Build query based on selected tab and filters (strictly scoped to active warehouse domain variants)
        $query = ConsumptionSnapshot::whereHas('variant')->with(['variant.item']);

        // Tab Ranking logic
        switch ($this->rankingTab) {
            case 'TOP_RISING':
                $query->whereIn('classification', [
                    ConsumptionCalculationService::CLASS_STRONG_RISING,
                    ConsumptionCalculationService::CLASS_RISING,
                    ConsumptionCalculationService::CLASS_WATCH,
                ])->orderByDesc('trend_index');
                break;

            case 'TOP_ACCELERATING':
                $query->where('active_weeks_count', '>=', 2)
                    ->where('total_consumption_qty', '>=', 5)
                    ->orderByDesc('recent_growth_pct');
                break;

            case 'TOP_VOLATILE':
                $query->where('active_weeks_count', '>=', 3)
                    ->orderByDesc('volatility_cv');
                break;

            case 'TOP_CONSUMPTION':
                $query->where('total_consumption_qty', '>', 0)
                    ->orderByDesc('total_consumption_qty');
                break;

            case 'ALL_ACTIVE':
            default:
                $query->where('active_weeks_count', '>', 0)
                    ->orderByDesc('trend_index');
                break;
        }

        // Apply Search filter
        if (!empty($this->search)) {
            $term = trim($this->search);
            $query->whereHas('variant', function ($vq) use ($term) {
                $vq->where('erp_code', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%")
                    ->orWhereHas('item', function ($iq) use ($term) {
                        $iq->where('name', 'like', "%{$term}%");
                    });
            });
        }

        // Apply Classification filter
        if (!empty($this->filterClassification)) {
            $query->where('classification', $this->filterClassification);
        }

        // Apply Department filter
        if (!empty($this->filterDepartment)) {
            $query->where('primary_department_driver', 'like', "%{$this->filterDepartment}%");
        }

        // Fetch Top 10 items for visual chart representation
        $topVisualItems = (clone $query)->limit(10)->get();

        $snapshots = $query->paginate($this->perPage);

        // Fetch Department list for dropdown
        $departments = Department::orderBy('name')->pluck('name')->toArray();

        return view('livewire.intelligence.consumption-intelligence-page', [
            'snapshots'      => $snapshots,
            'kpis'           => $kpis,
            'latestPeriod'   => $latestPeriod,
            'departments'    => $departments,
            'topVisualItems' => $topVisualItems,
        ])->layout('layouts.app');
    }
}
