<?php

namespace App\Livewire\Governance;

use Livewire\Component;
use App\Models\Warehouse;
use App\Models\Bin;
use App\Models\StockOpnameItem;
use App\Models\User;
use Carbon\Carbon;

class AuditCoveragePage extends Component
{
    public $warehouseId;
    
    // Autocomplete Search State for Bin Location Code
    public $binSearch = '';
    public $selectedBinCode = null;
    public $binDropdownOpen = false;

    // Report State
    public $activeBinCode = null;
    public $hasGenerated = false;

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
        $this->selectedBinCode = null;
        $this->binSearch = '';
        $this->activeBinCode = null;
        $this->hasGenerated = false;
        $this->binDropdownOpen = false;
    }

    public function updatedBinSearch($value)
    {
        if (empty($value)) {
            $this->selectedBinCode = null;
        } else {
            $this->binDropdownOpen = true;
        }
    }

    public function selectBinCode($code)
    {
        $this->selectedBinCode = $code;
        $this->binSearch = $code;
        $this->binDropdownOpen = false;
    }

    public function resetBinCode()
    {
        $this->selectedBinCode = null;
        $this->binSearch = '';
        $this->binDropdownOpen = false;
    }

    public function generateCoverage()
    {
        $this->validate([
            'selectedBinCode' => 'required|string',
        ]);

        $this->activeBinCode = $this->selectedBinCode;
        $this->hasGenerated = true;
    }

    public function setQuickFilter($filter)
    {
        if (in_array($filter, ['all', 'audited', 'needs_audit', 'stale'])) {
            $this->quickFilter = $filter;
        }
    }

    /**
     * Get autocomplete bin codes for search query.
     */
    public function getBinOptions()
    {
        if (!$this->warehouseId) {
            return collect();
        }

        return Bin::where('warehouse_id', $this->warehouseId)
            ->when($this->binSearch, function($query) {
                $query->where('code', 'like', $this->binSearch . '%');
            })
            ->select('code')
            ->distinct()
            ->orderBy('code')
            ->limit(15)
            ->pluck('code');
    }

    public function render()
    {
        $warehouses = auth()->user()->warehouses;
        $binOptions = $this->getBinOptions();

        $items = collect();
        $summary = [
            'total' => 0,
            'audited' => 0,
            'aging' => 0,
            'needs_audit' => 0,
            'coverage' => 0
        ];

        // Query only runs if we have generated and have an active bin code
        if ($this->hasGenerated && $this->warehouseId && $this->activeBinCode) {
            $bins = Bin::where('warehouse_id', $this->warehouseId)
                ->where('code', $this->activeBinCode)
                ->with(['itemVariant.item'])
                ->get();

            if ($bins->isNotEmpty()) {
                // Fetch the latest Physical Opname record for each bin
                $opnames = StockOpnameItem::whereIn('bin_id', $bins->pluck('id'))
                    ->join('stock_opnames', 'stock_opnames.id', '=', 'stock_opname_items.stock_opname_id')
                    ->select('stock_opname_items.*', 'stock_opnames.created_by', 'stock_opnames.created_at as opname_date')
                    ->orderBy('stock_opname_items.created_at', 'desc')
                    ->get()
                    ->groupBy('bin_id');

                // Extract unique auditor (created_by) user IDs
                $userIds = [];
                foreach ($opnames as $binId => $itemsList) {
                    $latestOpn = $itemsList->first();
                    if ($latestOpn && $latestOpn->created_by) {
                        $userIds[] = (int)$latestOpn->created_by;
                    }
                }

                // Bulk query user names to avoid N+1 queries
                $userNames = User::whereIn('id', array_unique($userIds))->pluck('name', 'id');

                $greenDays = (int)config('wms.audit_green_days', 60);
                $yellowDays = (int)config('wms.audit_yellow_days', 120);
                $now = Carbon::now();

                $auditedCount = 0;
                $agingCount = 0;
                $needsAuditCount = 0;

                // Process bins
                $processed = $bins->map(function($bin) use ($opnames, $userNames, $greenDays, $yellowDays, $now, &$auditedCount, &$agingCount, &$needsAuditCount) {
                    $latestOpn = $opnames->has($bin->id) ? $opnames->get($bin->id)->first() : null;

                    $lastAuditDate = null;
                    $lastAuditTimestamp = 0;
                    $age = 'Never';
                    $lastAuditor = 'N/A';
                    $auditNote = 'No physical audit record found.';
                    $status = 'red';
                    $statusLabel = 'Needs Audit';
                    
                    $nextDueDate = null;
                    $isOverdue = false;
                    $overdueDays = 0;

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

                        // Next Due / Overdue calculations (Last Audit + 120 days)
                        $nextDue = $lastAuditDate->copy()->addDays(120);
                        $nextDueDate = $nextDue->format('d M Y');
                        $isOverdue = $nextDue->isPast();
                        if ($isOverdue) {
                            $overdueDays = (int)$nextDue->diffInDays($now);
                        }

                        if ($daysAgo <= $greenDays) {
                            $status = 'green';
                            $statusLabel = 'Audited';
                            $auditedCount++;
                        } elseif ($daysAgo <= $yellowDays) {
                            $status = 'yellow';
                            $statusLabel = 'Audit Aging';
                            $agingCount++;
                        } else {
                            $status = 'red';
                            $statusLabel = 'Needs Audit';
                            $needsAuditCount++;
                        }
                    } else {
                        $needsAuditCount++;
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
                        'status_label' => $statusLabel,
                        'next_due_date' => $nextDueDate,
                        'is_overdue' => $isOverdue,
                        'overdue_days' => $overdueDays,
                    ];
                });

                // Calculate summary card stats
                $total = $processed->count();
                $coverage = $total > 0 ? round(($auditedCount / $total) * 100) : 0;

                $summary = [
                    'total' => $total,
                    'audited' => $auditedCount,
                    'aging' => $agingCount,
                    'needs_audit' => $needsAuditCount,
                    'coverage' => $coverage
                ];

                // Default Sorting: Oldest Last Audit first (Ascending)
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
            'binOptions' => $binOptions,
            'items' => $items,
            'summary' => $summary
        ])->layout('layouts.app');
    }
}
