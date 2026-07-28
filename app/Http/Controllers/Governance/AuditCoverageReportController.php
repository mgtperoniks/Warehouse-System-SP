<?php

namespace App\Http\Controllers\Governance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Warehouse;
use App\Models\Bin;
use App\Models\StockOpnameItem;
use App\Models\User;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class AuditCoverageReportController extends Controller
{
    /**
     * Generate and stream the Audit Coverage PDF inline in the browser.
     */
    public function viewPdf(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        $warehouseId = $request->input('warehouse_id');
        $binCode = $request->input('bin_code');
        $filter = $request->input('filter', 'all');

        if (!$warehouseId || !$binCode) {
            abort(400, 'Missing required parameters.');
        }

        // Verify warehouse access
        $isMapped = $user->warehouses()->where('warehouses.id', $warehouseId)->exists();
        if (!$isMapped) {
            abort(403, 'Unauthorized.');
        }

        $warehouse = Warehouse::find($warehouseId);
        if (!$warehouse) {
            abort(404, 'Warehouse not found.');
        }

        // Fetch bins in the location
        $bins = Bin::where('warehouse_id', $warehouseId)
            ->where('code', $binCode)
            ->with(['itemVariant.item'])
            ->get();

        $items = collect();
        $summary = [
            'total' => 0,
            'audited' => 0,
            'aging' => 0,
            'needs_audit' => 0,
            'coverage' => 0
        ];

        if ($bins->isNotEmpty()) {
            // Fetch the latest Physical Opname record for each bin
            $opnames = StockOpnameItem::whereIn('bin_id', $bins->pluck('id'))
                ->join('stock_opnames', 'stock_opnames.id', '=', 'stock_opname_items.stock_opname_id')
                ->select('stock_opname_items.*', 'stock_opnames.created_by', 'stock_opnames.created_at as opname_date')
                ->orderBy('stock_opname_items.created_at', 'desc')
                ->get()
                ->groupBy('bin_id');

            // Extract unique auditor user IDs
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
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'next_due_date' => $nextDueDate,
                    'is_overdue' => $isOverdue,
                    'overdue_days' => $overdueDays,
                ];
            });

            // Calculate summary stats (unfiltered)
            $total = $processed->count();
            $coverage = $total > 0 ? round(($auditedCount / $total) * 100) : 0;

            $summary = [
                'total' => $total,
                'audited' => $auditedCount,
                'aging' => $agingCount,
                'needs_audit' => $needsAuditCount,
                'coverage' => $coverage
            ];

            // Default Sorting: Oldest Last Audit first
            $items = $processed->sortBy(function($item) {
                return $item->last_audit_timestamp;
            })->values();

            // Apply quick filters if active
            if ($filter === 'audited') {
                $items = $items->where('status', 'green')->values();
            } elseif ($filter === 'needs_audit') {
                $items = $items->where('status', 'red')->values();
            } elseif ($filter === 'stale') {
                $items = $items->where('status', 'yellow')->values();
            }
        }

        // Generate ACR reference document number (ACR-[Ymd]-[CleanedBinCode])
        $cleanBinCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', $binCode));
        $docNumber = 'ACR-' . now()->format('Ymd') . '-' . ($cleanBinCode ?: 'LOC');

        $pdf = Pdf::loadView('reports.audit-coverage-pdf', [
            'docNumber' => $docNumber,
            'warehouseName' => $warehouse->name,
            'binCode' => $binCode,
            'filterState' => $filter,
            'generatedDate' => now()->timezone('Asia/Jakarta')->translatedFormat('d M Y'),
            'generatedTime' => now()->timezone('Asia/Jakarta')->translatedFormat('H:i:s'),
            'generatedBy' => $user->name,
            'generationTimestamp' => now()->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'summary' => $summary,
            'items' => $items,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream($docNumber . '.pdf');
    }
}
