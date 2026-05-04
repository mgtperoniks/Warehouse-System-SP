<?php

namespace App\Repositories;

use App\Models\PrintJob;
use App\Models\PrintLog;
use Illuminate\Support\Facades\Auth;

class PrintLogRepository
{
    /**
     * Record a PRINT action.
     */
    public function recordPrint(PrintJob $job): PrintLog
    {
        return $this->write($job, PrintLog::ACTION_PRINT);
    }

    /**
     * Record a REPRINT action with mandatory reason.
     */
    public function recordReprint(PrintJob $job, string $reason): PrintLog
    {
        return $this->write($job, PrintLog::ACTION_REPRINT, $reason);
    }

    /**
     * Record a CANCEL action.
     */
    public function recordCancel(PrintJob $job, ?string $reason = null): PrintLog
    {
        return $this->write($job, PrintLog::ACTION_CANCEL, $reason);
    }

    /**
     * Record a FAILED action with error detail.
     */
    public function recordFailed(PrintJob $job, string $errorMessage): PrintLog
    {
        return $this->write($job, PrintLog::ACTION_FAILED, $errorMessage);
    }

    /**
     * Get the full print history for an item variant.
     */
    public function getHistoryForVariant(int $variantId, int $limit = 20)
    {
        return PrintLog::with(['user', 'printJob.printer', 'printJob.template'])
            ->where('item_variant_id', $variantId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    // ─── Private ────────────────────────────────────────────────────────────────

    private function write(PrintJob $job, string $action, ?string $reason = null): PrintLog
    {
        return PrintLog::create([
            'print_job_id'    => $job->id,
            'item_variant_id' => $job->item_variant_id,
            'action_type'     => $action,
            'action_reason'   => $reason,
            'user_id'         => Auth::id(),
            'metadata'        => [
                'copies'         => $job->copies,
                'barcode_value'  => $job->barcode_value,
                'template_code'  => $job->template?->template_code,
                'printer_code'   => $job->printer?->printer_code,
                'printer_ip'     => $job->printer?->printer_ip,
            ],
        ]);
    }
}
