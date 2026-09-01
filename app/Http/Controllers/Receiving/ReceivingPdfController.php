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
     * View or download the Receiving Inspection PDF (Bukti Pengecekan Barang Datang).
     * Supports preview in READY_REVIEW / REVIEWED and stored file streaming in COMPLETED.
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

        // Only allow generated PDFs for verified / reviewed / completed sessions
        if ($session->status === ReceivingSession::STATUS_DRAFT) {
            abort(400, 'Receiving session is still in DRAFT status. Complete checking first to generate A4 form.');
        }

        $pdfPath = $session->pdf_path ?: 'receiving/receiving_session_' . $session->id . '.pdf';

        if ($session->status === ReceivingSession::STATUS_COMPLETED && Storage::disk('public')->exists($pdfPath)) {
            return response()->file(Storage::disk('public')->path($pdfPath), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . basename($pdfPath) . '"'
            ]);
        }

        // Generate PDF view dynamically
        $items = $session->items()->with(['outstandingPurchaseOrderItem', 'variant.item'])->get();
        $signatures = ReceivingSignature::where('receiving_session_id', $session->id)->get();

        $sigMap = [];
        foreach ($signatures as $sig) {
            if (Storage::disk('public')->exists($sig->signature_path)) {
                $raw = Storage::disk('public')->get($sig->signature_path);
                $trimmed = self::trimSignaturePng($raw);
                $sig->base64_data = 'data:image/png;base64,' . base64_encode($trimmed);
                $sigMap[$sig->role] = $sig;
            }
        }

        $pdf = Pdf::loadView('reports.receiving-inspection-pdf', [
            'session' => $session,
            'items' => $items,
            'signatures' => $sigMap,
        ])->setPaper('a4', 'portrait');

        if ($session->status === ReceivingSession::STATUS_COMPLETED) {
            Storage::disk('public')->put($pdfPath, $pdf->output());
            $session->pdf_path = $pdfPath;
            $session->save();
        }

        return $pdf->stream('BUKTI_PENGECEKAN_PO_' . ($session->outstandingPurchaseOrder->po_number ?? $session->id) . '.pdf');
    }

    /**
     * Trim transparent whitespace bounding box around handwritten signature PNG binary
     */
    public static function trimSignaturePng($pngBinary, $padding = 12)
    {
        if (empty($pngBinary) || !extension_loaded('gd')) {
            return $pngBinary;
        }

        $src = @imagecreatefromstring($pngBinary);
        if (!$src) {
            return $pngBinary;
        }

        $w = imagesx($src);
        $h = imagesy($src);

        $minX = $w; $minY = $h; $maxX = 0; $maxY = 0;
        $hasContent = false;

        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgba = imagecolorat($src, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha < 120) { // non-transparent pixel
                    $hasContent = true;
                    if ($x < $minX) $minX = $x;
                    if ($x > $maxX) $maxX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }

        if (!$hasContent) {
            imagedestroy($src);
            return $pngBinary;
        }

        $cropX = max(0, $minX - $padding);
        $cropY = max(0, $minY - $padding);
        $cropW = min($w - $cropX, ($maxX - $minX + 1) + ($padding * 2));
        $cropH = min($h - $cropY, ($maxY - $minY + 1) + ($padding * 2));

        $cropped = imagecrop($src, [
            'x' => $cropX,
            'y' => $cropY,
            'width' => $cropW,
            'height' => $cropH
        ]);

        imagedestroy($src);

        if (!$cropped) {
            return $pngBinary;
        }

        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);

        ob_start();
        imagepng($cropped);
        $result = ob_get_clean();
        imagedestroy($cropped);

        return $result;
    }
}
