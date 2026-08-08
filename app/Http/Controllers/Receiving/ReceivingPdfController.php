<?php

namespace App\Http\Controllers\Receiving;

use App\Http\Controllers\Controller;
use App\Models\ReceivingSession;
use App\Models\ReceivingSignature;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceivingPdfController extends Controller
{
    /**
     * View the Receiving Inspection PDF inline inside a new tab.
     * Regenerates the file dynamically if missing from storage.
     */
    public function view($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthenticated.');
        }

        $session = ReceivingSession::with(['outstandingPurchaseOrder'])->findOrFail($id);

        // Warehouse Isolation Check
        $activeWarehouseId = session()->get('active_warehouse_id');
        if ($session->warehouse_id != $activeWarehouseId) {
            abort(403, 'Unauthorized warehouse context.');
        }

        // Only allow generated PDFs for Completed sessions
        if ($session->status !== ReceivingSession::STATUS_COMPLETED) {
            abort(400, 'Receiving session is not finalized yet.');
        }

        $pdfPath = $session->pdf_path ?: 'receiving/receiving_session_' . $session->id . '.pdf';

        if (!Storage::disk('public')->exists($pdfPath)) {
            // Regenerate PDF
            $items = $session->items()->with(['outstandingPurchaseOrderItem', 'variant.item'])->get();
            $signatures = ReceivingSignature::where('receiving_session_id', $session->id)->get();

            $sigMap = [];
            foreach ($signatures as $sig) {
                $sigMap[$sig->role] = $sig;
            }

            $pdf = Pdf::loadView('reports.receiving-inspection-pdf', [
                'session' => $session,
                'items' => $items,
                'signatures' => $sigMap,
            ])->setPaper('a4', 'portrait');

            Storage::disk('public')->put($pdfPath, $pdf->output());

            // Save the regenerated path
            $session->pdf_path = $pdfPath;
            $session->save();
        }

        return response()->file(Storage::disk('public')->path($pdfPath), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($pdfPath) . '"'
        ]);
    }
}
