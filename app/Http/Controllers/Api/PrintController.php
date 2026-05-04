<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Repositories\PrintLogRepository;
use App\Repositories\PrinterRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function __construct(
        private readonly PrinterRepository $printerRepo,
        private readonly PrintLogRepository $logRepo,
    ) {}

    /**
     * GET /api/printers
     * List all active printers with their current status.
     */
    public function printers(): JsonResponse
    {
        $printers = $this->printerRepo->getActivePrinters();

        return response()->json([
            'data' => $printers->map(fn (Printer $p) => [
                'id'            => $p->id,
                'printer_code'  => $p->printer_code,
                'printer_name'  => $p->printer_name,
                'location'      => $p->location,
                'status'        => $p->status,
                'is_online'     => $p->is_online,
                'last_heartbeat'=> $p->last_heartbeat,
                'dpi'           => $p->dpi,
            ]),
        ]);
    }

    /**
     * GET /api/printers/{id}/ping
     * Perform a live TCP health check on a specific printer.
     */
    public function ping(Printer $printer): JsonResponse
    {
        $isOnline = $this->printerRepo->ping($printer);

        return response()->json([
            'printer_code' => $printer->printer_code,
            'status'       => $isOnline ? 'online' : 'offline',
            'is_online'    => $isOnline,
            'checked_at'   => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/print-jobs?variant_id=
     * Get current queue status for a specific item variant or globally.
     */
    public function jobs(Request $request): JsonResponse
    {
        $query = PrintJob::with(['printer:id,printer_name,status', 'template:id,template_name'])
            ->orderByDesc('created_at');

        if ($request->filled('variant_id')) {
            $query->where('item_variant_id', $request->integer('variant_id'));
        }

        $jobs = $query->limit(50)->get();

        $summary = [
            'pending'    => $jobs->where('status', PrintJob::STATUS_PENDING)->count(),
            'processing' => $jobs->where('status', PrintJob::STATUS_PROCESSING)->count(),
            'completed'  => $jobs->where('status', PrintJob::STATUS_COMPLETED)->count(),
            'failed'     => $jobs->where('status', PrintJob::STATUS_FAILED)->count(),
        ];

        return response()->json([
            'summary' => $summary,
            'jobs'    => $jobs->map(fn (PrintJob $j) => [
                'id'           => $j->id,
                'job_uuid'     => $j->job_uuid,
                'status'       => $j->status,
                'copies'       => $j->copies,
                'barcode_value'=> $j->barcode_value,
                'printer'      => $j->printer?->printer_name,
                'template'     => $j->template?->template_name,
                'created_at'   => $j->created_at?->diffForHumans(),
                'printed_at'   => $j->printed_at?->diffForHumans(),
                'error_message'=> $j->error_message,
                'retry_count'  => $j->retry_count,
            ]),
        ]);
    }

    /**
     * POST /api/print-jobs/{job}/cancel
     * Cancel a pending print job.
     */
    public function cancelJob(PrintJob $job): JsonResponse
    {
        if ($job->status !== PrintJob::STATUS_PENDING) {
            return response()->json([
                'error' => "Job cannot be cancelled because it is already [{$job->status}].",
            ], 422);
        }

        $job->update(['status' => PrintJob::STATUS_CANCELLED]);

        $this->logRepo->recordCancel($job, 'Cancelled by operator');

        return response()->json(['message' => 'Job cancelled.', 'job_uuid' => $job->job_uuid]);
    }

    /**
     * GET /api/print-history/{variantId}
     * Get the full print history for an item variant.
     */
    public function history(int $variantId): JsonResponse
    {
        $logs = $this->logRepo->getHistoryForVariant($variantId);

        return response()->json([
            'data' => $logs->map(fn ($log) => [
                'id'            => $log->id,
                'action_type'   => $log->action_type,
                'action_reason' => $log->action_reason,
                'badge_color'   => $log->badge_color,
                'operator'      => $log->user?->name ?? 'System',
                'template'      => $log->printJob?->template?->template_name,
                'printer'       => $log->printJob?->printer?->printer_name,
                'copies'        => $log->metadata['copies'] ?? null,
                'barcode_value' => $log->metadata['barcode_value'] ?? null,
                'created_at'    => $log->created_at?->format('d M Y H:i'),
            ]),
        ]);
    }
}
