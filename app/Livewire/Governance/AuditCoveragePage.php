<?php

namespace App\Livewire\Governance;

use Livewire\Component;
use App\Models\Warehouse;
use App\Models\Location;
use App\Models\Bin;
use App\Models\StockOpnameItem;
use App\Models\User;
use Carbon\Carbon;

class AuditCoveragePage extends Component
{
    public $warehouseId;
    
    // Autocomplete Search State
    public $rackSearch = '';
    public $selectedRackId = null;
    public $selectedRackCode = null;
    public $rackDropdownOpen = false;

    public $subRackSearch = '';
    public $selectedSubRackCode = null;
    public $subRackDropdownOpen = false;

    // Quick Filter State ('all', 'audited', 'needs_audit', 'stale')
    public $quickFilter = 'all';

    public function mount()
    {
        $this->warehouseId = session('active_warehouse_id');
        
        // Fallback if no active warehouse is set
        if (!$this->warehouseId) {
            $firstWh = auth()->user()->warehouses->first();
            if ($firstWh) {
                $this->warehouseId = $firstWh->id;
            }
        }
    }

    public function updatedWarehouseId()
    {
        $this->resetRack();
    }

    public function updatedRackSearch($value)
    {
        if (empty($value)) {
            $this->selectedRackId = null;
            $this->selectedRackCode = null;
            $this->resetSubRack();
        } else {
            $this->rackDropdownOpen = true;
        }
    }

    public function updatedSubRackSearch($value)
    {
        if (empty($value)) {
            $this->selectedSubRackCode = null;
        } else {
            $this->subRackDropdownOpen = true;
        }
    }

    public function selectRack($id, $code)
    {
        $this->selectedRackId = $id;
        $this->selectedRackCode = $code;
        $this->rackSearch = $code;
        $this->rackDropdownOpen = false;
        $this->resetSubRack();
    }

    public function selectSubRack($code)
    {
        $this->selectedSubRackCode = $code;
        $this->subRackSearch = $code;
        $this->subRackDropdownOpen = false;
    }

    public function resetRack()
    {
        $this->selectedRackId = null;
        $this->selectedRackCode = null;
        $this->rackSearch = '';
        $this->rackDropdownOpen = false;
        $this->resetSubRack();
    }

    public function resetSubRack()
    {
        $this->selectedSubRackCode = null;
        $this->subRackSearch = '';
        $this->subRackDropdownOpen = false;
    }

    public function setQuickFilter($filter)
    {
        if (in_array($filter, ['all', 'audited', 'needs_audit', 'stale'])) {
            $this->quickFilter = $filter;
        }
    }

    /**
     * Get autocomplete locations/racks for search query.
     */
    public function getRackOptions()
    {
        if (!$this->warehouseId) {
            return collect();
        }

        return Location::whereHas('bins', function($q) {
            $q->where('warehouse_id', $this->warehouseId);
        })
        ->when($this->rackSearch, function($query) {
            $query->where(function($q) {
                $q->where('code', 'like', '%' . $this->rackSearch . '%')
                  ->orWhere('description', 'like', '%' . $this->rackSearch . '%');
            });
        })
        ->orderBy('code')
        ->limit(10)
        ->get();
    }

    /**
     * Get autocomplete bin codes/sub racks for search query under selected location.
     */
    public function getSubRackOptions()
    {
        if (!$this->warehouseId || !$this->selectedRackId) {
            return collect();
        }

        return Bin::where('warehouse_id', $this->warehouseId)
            ->where('location_id', $this->selectedRackId)
            ->when($this->subRackSearch, function($query) {
                $query->where('code', 'like', '%' . $this->subRackSearch . '%');
            })
            ->select('code')
            ->distinct()
            ->orderBy('code')
            ->limit(10)
            ->pluck('code');
    }

    public function render()
    {
        $warehouses = auth()->user()->warehouses;
        
        $rackOptions = $this->getRackOptions();
        $subRackOptions = $this->getSubRackOptions();

        $binsQuery = null;
        $items = collect();
        $summary = [
            'total' => 0,
            'audited' => 0,
            'needs_audit' => 0,
            'coverage' => 0
        ];

        // Only query list and summary if a Rack is selected
        if ($this->warehouseId && $this->selectedRackId) {
            $binsQuery = Bin::where('warehouse_id', $this->warehouseId)
                ->where('location_id', $this->selectedRackId)
                ->when($this->selectedSubRackCode, function($query) {
                    $query->where('code', $this->selectedSubRackCode);
                });

            $bins = $binsQuery->with(['itemVariant.item'])->get();

            if ($bins->isNotEmpty()) {
                // Fetch the latest Physical Opname record for each bin
                $opnames = StockOpnameItem::whereIn('bin_id', $bins->pluck('id'))
                    ->join('stock_opnames', 'stock_opnames.id', '=', 'stock_opname_items.stock_opname_id')
                    ->select('stock_opname_items.*', 'stock_opnames.created_by', 'stock_opnames.created_at as opname_date')
                    ->orderBy('stock_opname_items.created_at', 'desc')
                    ->get()
                    ->groupBy('bin_id');

                // Extract all unique auditor (created_by) user IDs
                $userIds = [];
                foreach ($opnames as $binId => $itemsList) {
                    $latestOpn = $itemsList->first();
                    if ($latestOpn && $latestOpn->created_by) {
                        $userIds[] = (int)$latestOpn->created_by;
                    }
                }

                // Bulk query user names to avoid N+1 queries
                $userNames = User::whereIn('id', array_unique($userIds))->pluck('name', 'id');

                $greenDays = (int)config('wms.audit_green_days', 30);
                $yellowDays = (int)config('wms.audit_yellow_days', 90);
                $now = Carbon::now();

                $auditedCount = 0;

                // Process bins list
                $processed = $bins->map(function($bin) use ($opnames, $userNames, $greenDays, $yellowDays, $now, &$auditedCount) {
                    $latestOpn = $opnames->has($bin->id) ? $opnames->get($bin->id)->first() : null;

                    $lastAuditDate = null;
                    $lastAuditTimestamp = 0;
                    $age = 'Never';
                    $lastAuditor = 'N/A';
                    $auditNote = 'No physical audit record found.';
                    $status = 'red';
                    $statusLabel = 'Needs Audit';

                    if ($latestOpn) {
                        $lastAuditDate = Carbon::parse($latestOpn->opname_date);
                        $lastAuditTimestamp = $lastAuditDate->timestamp;
                        
                        $daysAgo = (int)$lastAuditDate->diffInDays($now);
                        $age = $daysAgo === 0 ? 'today' : ($daysAgo === 1 ? 'yesterday' : $daysAgo . ' days ago');

                        if ($latestOpn->created_by && isset($userNames[$latestOpn->created_by])) {
                            $lastAuditor = $userNames[$latestOpn->created_by];
                        } else {
                            $lastAuditor = 'System';
                        }

                        if ($latestOpn->difference === 0) {
                            $auditNote = 'Verified: Stock matches system quantity (' . $latestOpn->system_qty . ' units).';
                        } else {
                            $auditNote = 'Discrepancy: System ' . $latestOpn->system_qty . ', Actual ' . $latestOpn->actual_qty . ' (Diff: ' . ($latestOpn->difference > 0 ? '+' : '') . $latestOpn->difference . ').';
                        }

                        if ($daysAgo <= $greenDays) {
                            $status = 'green';
                            $statusLabel = 'Audited';
                            $auditedCount++;
                        } elseif ($daysAgo <= $yellowDays) {
                            $status = 'yellow';
                            $statusLabel = 'Audit Aging';
                        } else {
                            $status = 'red';
                            $statusLabel = 'Needs Audit';
                        }
                    }

                    return (object)[
                        'bin_id' => $bin->id,
                        'bin_code' => $bin->code,
                        'item_code' => $bin->itemVariant->erp_code ?? $bin->itemVariant->sku ?? 'N/A',
                        'item_name' => $bin->itemVariant->item->name ?? 'N/A',
                        'current_stock' => $bin->current_qty,
                        'last_audit_date' => $lastAuditDate ? $lastAuditDate->format('d M Y') : 'NEVER',
                        'last_audit_timestamp' => $lastAuditTimestamp,
                        'age' => $age,
                        'last_auditor' => $lastAuditor,
                        'audit_note' => $auditNote,
                        'status' => $status,
                        'status_label' => $statusLabel
                    ];
                });

                // Calculate summary card stats (based on all items, unfiltered by quick filter)
                $total = $processed->count();
                $needs_audit = $total - $auditedCount;
                $coverage = $total > 0 ? round(($auditedCount / $total) * 100) : 0;

                $summary = [
                    'total' => $total,
                    'audited' => $auditedCount,
                    'needs_audit' => $needs_audit,
                    'coverage' => $coverage
                ];

                // Apply Default Sorting: Oldest Last Audit first (Ascending)
                // Items with last_audit_timestamp = 0 (Never audited) appear first
                $items = $processed->sortBy(function($item) {
                    return $item->last_audit_timestamp;
                });

                // Apply Quick Filters
                if ($this->quickFilter === 'audited') {
                    $items = $items->where('status', 'green');
                } elseif ($this->quickFilter === 'needs_audit') {
                    $items = $items->where('status', 'red');
                } elseif ($this->quickFilter === 'stale') {
                    $items = $items->where('status', 'yellow');
                }
            }
        }

        return view('livewire.governance.audit-coverage-page', [
            'warehouses' => $warehouses,
            'rackOptions' => $rackOptions,
            'subRackOptions' => $subRackOptions,
            'items' => $items,
            'summary' => $summary
        ])->layout('layouts.app');
    }
}
