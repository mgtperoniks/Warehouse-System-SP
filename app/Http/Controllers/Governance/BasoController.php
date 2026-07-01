<?php

namespace App\Http\Controllers\Governance;

use App\Http\Controllers\Controller;
use App\Models\BasoDocument;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class BasoController extends Controller
{
    /**
     * View the BASO PDF inline inside a new tab.
     * Regenerates the file dynamically if missing.
     */
    public function view($id)
    {
        $baso = BasoDocument::findOrFail($id);
        $adjustment = $baso->inventoryAdjustment;

        if (!Storage::disk('public')->exists($baso->pdf_path)) {
            // Regenerate PDF
            $items = $adjustment->items()->with(['bin', 'itemVariant.item'])->get();
            $totalItems = $items->count();
            $approvedCount = $items->where('status', 'APPROVED')->count();
            $rejectedCount = $items->where('status', 'REJECTED')->count();
            
            $posVariance = $items->where('variance', '>', 0)->sum('variance');
            $negVariance = $items->where('variance', '<', 0)->sum('variance');
            $netVariance = $items->sum('variance');

            $warehouseName = $adjustment->warehouse->name ?? 'N/A';
            $operatorName = $adjustment->operator->name ?? 'N/A';
            $managerName = $baso->generator->name ?? 'N/A';
            $businessDate = $adjustment->date;

            $reasonsMap = [
                'COUNTING_ERROR' => 'Salah Hitung',
                'WRONG_SCAN' => 'Salah Scan Barcode',
                'WRONG_BIN' => 'Salah Rak / Salah Penempatan',
                'WRONG_PICK' => 'Salah Ambil Barang',
                'FOUND_ITEM' => 'Barang Ditemukan',
                'RETURN_FOUND' => 'Barang Retur Ditemukan',
                'LEFTOVER_FOUND' => 'Sisa Produksi Ditemukan',
                'MISSING_ITEM' => 'Barang Tidak Ditemukan',
                'DAMAGED_ITEM' => 'Barang Rusak',
                'EXPIRED_ITEM' => 'Barang Kadaluarsa / Tidak Layak Pakai',
                'MOVED_WITHOUT_SCAN' => 'Dipindahkan Tanpa Scan',
                'SYSTEM_GLITCH' => 'Glitch Sistem / Selisih Sinkronisasi',
                'LAINNYA' => 'Lainnya (Butuh Catatan)',
            ];

            $pdf = Pdf::loadView('reports.baso-pdf', [
                'baso' => $baso,
                'adjustment' => $adjustment,
                'items' => $items,
                'totalItems' => $totalItems,
                'approvedCount' => $approvedCount,
                'rejectedCount' => $rejectedCount,
                'posVariance' => $posVariance,
                'negVariance' => $negVariance,
                'netVariance' => $netVariance,
                'warehouseName' => $warehouseName,
                'operatorName' => $operatorName,
                'managerName' => $managerName,
                'businessDate' => $businessDate,
                'reasonsMap' => $reasonsMap,
            ])->setPaper('a4', 'portrait');

            Storage::disk('public')->put($baso->pdf_path, $pdf->output());
        }

        return response()->file(Storage::disk('public')->path($baso->pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($baso->pdf_path) . '"'
        ]);
    }
}
