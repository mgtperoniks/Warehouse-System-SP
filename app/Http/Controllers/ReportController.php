<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StockTransaction;
use App\Models\StockInReceipt;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Helper to apply filters to Stock Out query.
     */
    private function applyStockOutFilters($query, Request $request)
    {
        $query->forActiveWarehouse()
            ->where('type', 'OUT')
            ->where('status', 'CONFIRMED');

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('pic_id')) {
            $query->where('user_id', $request->pic_id);
        }
        if ($request->filled('code')) {
            $query->where('code', 'like', '%' . $request->code . '%');
        }
        if ($request->filled('erp_transfer_status')) {
            $query->where('erp_transfer_status', $request->erp_transfer_status);
        }

        return $query;
    }

    /**
     * Helper to apply filters to Stock In query.
     */
    private function applyStockInFilters($query, Request $request)
    {
        $query->forActiveWarehouse()
            ->where('status', 'COMMITTED');

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        if ($request->filled('operator_id')) {
            $query->where('operator_id', $request->operator_id);
        }
        if ($request->filled('receipt_code')) {
            $query->where('receipt_code', 'like', '%' . $request->receipt_code . '%');
        }
        if ($request->filled('erp_transfer_status')) {
            $query->where('erp_transfer_status', $request->erp_transfer_status);
        }

        return $query;
    }

    /**
     * Export Stock Out Report as flat CSV.
     */
    public function exportStockOutCsv(Request $request)
    {
        $query = StockTransaction::with(['department', 'user', 'items.variant.item']);
        $query = $this->applyStockOutFilters($query, $request);
        $transactions = $query->orderBy('created_at', 'desc')->get();

        $filename = "stock_out_bkb_" . Carbon::now()->format('Ymd_His') . ".csv";

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename={$filename}",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');
            
            // Raw simple header columns
            fputcsv($file, [
                'ERP_CODE',
                'ITEM_NAME',
                'QTY',
                'UNIT',
                'DEPARTMENT',
                'PIC',
                'REFERENCE',
                'TRANSACTION_CODE'
            ]);

            foreach ($transactions as $tx) {
                foreach ($tx->items as $item) {
                    fputcsv($file, [
                        $item->erp_code_snapshot ?? ($item->variant->erp_code ?? ''),
                        $item->item_name_snapshot ?? ($item->variant->item->name ?? ''),
                        $item->qty,
                        $item->unit_snapshot ?? ($item->variant->unit ?? 'PCS'),
                        $tx->department->name ?? 'N/A',
                        $tx->user->name ?? 'N/A',
                        $tx->reference ?? '',
                        $tx->code
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Stock In Report as flat CSV.
     */
    public function exportStockInCsv(Request $request)
    {
        $query = StockInReceipt::with(['operator', 'items.variant.item', 'items.bin']);
        $query = $this->applyStockInFilters($query, $request);
        $receipts = $query->orderBy('created_at', 'desc')->get();

        $filename = "stock_in_bpb_" . Carbon::now()->format('Ymd_His') . ".csv";

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename={$filename}",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($receipts) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'ERP_CODE',
                'ITEM_NAME',
                'QTY_RECEIVED',
                'BIN',
                'RECEIPT_CODE',
                'OPERATOR',
                'DATE'
            ]);

            foreach ($receipts as $receipt) {
                foreach ($receipt->items as $item) {
                    fputcsv($file, [
                        $item->variant->erp_code ?? '',
                        $item->variant->item->name ?? '',
                        $item->qty,
                        $item->bin->code ?? '',
                        $receipt->receipt_code,
                        $receipt->operator->name ?? 'N/A',
                        $receipt->created_at->format('Y-m-d H:i')
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Professional preview layout for Stock Out (BKB)
     */
    public function previewStockOut(Request $request)
    {
        $query = StockTransaction::with(['department', 'user', 'items.variant.item']);
        $query = $this->applyStockOutFilters($query, $request);
        $transactions = $query->orderBy('created_at', 'desc')->get();

        return view('reports.stock-out-preview', compact('transactions'));
    }

    /**
     * Printable A4 layout for Stock Out (BKB)
     */
    public function printStockOut(Request $request)
    {
        $query = StockTransaction::with(['department', 'user', 'items.variant.item']);
        $query = $this->applyStockOutFilters($query, $request);
        $transactions = $query->orderBy('created_at', 'desc')->get();

        return view('reports.stock-out-print', compact('transactions'));
    }

    /**
     * Printable A4 layout for Stock In (BPB)
     */
    public function printStockIn(Request $request)
    {
        $query = StockInReceipt::with(['operator', 'items.variant.item', 'items.bin', 'supplier']);
        $query = $this->applyStockInFilters($query, $request);
        $receipts = $query->orderBy('created_at', 'desc')->get();

        return view('reports.stock-in-print', compact('receipts'));
    }

    /**
     * Printable A4 Landscape layout for Movement Ledger (Kartu Stok)
     */
    public function printMovementLedger(Request $request, \App\Services\Reports\MovementLedgerService $ledgerService)
    {
        $request->validate([
            'selectedVariantId' => 'required|integer',
            'startDate' => 'required|date',
            'endDate' => 'required|date',
        ]);

        $variantId = $request->selectedVariantId;
        $startDate = $request->startDate;
        $endDate = $request->endDate;
        $movementType = $request->get('movementType', 'ALL');

        $variant = \App\Models\ItemVariant::with('item')->findOrFail($variantId);
        
        $movements = $ledgerService->getLedgerQuery($variantId, $startDate, $endDate, $movementType)->get();
        $startingBalance = $ledgerService->getStartingBalance($variantId, $startDate);

        if ($movements->count() > 200) {
            return response("Operational Constraint Violation: Printing report is restricted to 200 rows of movements. Please use Excel Export for large datasets.", 403);
        }

        return view('reports.movement-ledger-print', compact('variant', 'movements', 'startingBalance', 'startDate', 'endDate', 'movementType'));
    }

    /**
     * Memory-safe streaming CSV/Excel Export for Movement Ledger
     */
    public function exportMovementLedgerCsv(Request $request, \App\Services\Reports\MovementLedgerService $ledgerService)
    {
        $request->validate([
            'selectedVariantId' => 'required|integer',
            'startDate' => 'required|date',
            'endDate' => 'required|date',
        ]);

        $variantId = $request->selectedVariantId;
        $startDate = $request->startDate;
        $endDate = $request->endDate;
        $movementType = $request->get('movementType', 'ALL');

        $variant = \App\Models\ItemVariant::with('item')->findOrFail($variantId);
        $startingBalance = $ledgerService->getStartingBalance($variantId, $startDate);

        $filename = "kartu-stok-" . $variant->erp_code . "-" . date('Ymd-His') . ".csv";

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=" . $filename,
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($variantId, $startDate, $endDate, $movementType, $ledgerService, $startingBalance) {
            $file = fopen('php://output', 'w');
            
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'DATE',
                'REFERENCE',
                'DEPARTMENT / PIC / SUPPLIER',
                'TYPE',
                'QTY IN',
                'QTY OUT',
                'RUNNING BALANCE',
                'LOCATION',
                'OPERATOR'
            ]);

            fputcsv($file, [
                '-',
                'INIT_BALANCE',
                'Saldo awal sebelum rentang tanggal terpilih',
                '-',
                '-',
                '-',
                $startingBalance,
                '-',
                'SYSTEM'
            ]);

            $runningBalance = $startingBalance;

            $ledgerService->getLedgerQuery($variantId, $startDate, $endDate, $movementType)
                ->chunk(250, function($movements) use ($file, &$runningBalance) {
                    foreach ($movements as $mov) {
                        $runningBalance += $mov->qty;

                        $picDept = 'N/A';
                        if ($mov->type === 'IN') {
                            if ($mov->receipt) {
                                $picDept = ($mov->receipt->supplier ? $mov->receipt->supplier->name : 'BPB Inbound') . ' / ' . ($mov->receipt->operator ? $mov->receipt->operator->name : 'Operator');
                            } elseif ($mov->supplier) {
                                $picDept = $mov->supplier->name . ' / Inbound';
                            } else {
                                $picDept = 'General Stock In';
                            }
                        } elseif ($mov->type === 'OUT') {
                            if ($mov->transaction) {
                                $picDept = ($mov->transaction->department ? $mov->transaction->department->name : 'General') . ' / ' . ($mov->transaction->user ? $mov->transaction->user->name : 'PIC');
                            } else {
                                $picDept = 'Direct Checkout';
                            }
                        } elseif ($mov->type === 'ADJUSTMENT' || $mov->type === 'REVERSAL') {
                            $picDept = 'Opname Adjustment / Reversal';
                        }

                        $qtyIn = $mov->qty > 0 ? $mov->qty : 0;
                        $qtyOut = $mov->qty < 0 ? abs($mov->qty) : 0;

                        fputcsv($file, [
                            $mov->created_at->format('Y-m-d H:i:s'),
                            $mov->reference ?: 'SYSTEM_GEN',
                            strtoupper($picDept),
                            $mov->type,
                            $qtyIn ?: '-',
                            $qtyOut ?: '-',
                            $runningBalance,
                            $mov->bin ? $mov->bin->code : 'UNASSIGND',
                            strtoupper($mov->operator ? $mov->operator->name : ($mov->created_by ?: 'System'))
                        ]);
                    }
                });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
