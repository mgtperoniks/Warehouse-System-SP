<?php

namespace App\Jobs;

use App\Models\PrintJob;
use App\Repositories\PrintLogRepository;
use App\Repositories\PrinterRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPrintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Max attempts before the job is marked as permanently failed.
     */
    public int $tries = 3;

    /**
     * Wait 30 seconds between retries (exponential backoff).
     */
    public int $backoff = 30;

    /**
     * Timeout per attempt in seconds.
     */
    public int $timeout = 30;

    public function __construct(
        public readonly PrintJob $printJob
    ) {}

    // ─── Handle ──────────────────────────────────────────────────────────────────

    public function handle(
        PrinterRepository $printerRepo,
        PrintLogRepository $logRepo
    ): void {
        $job = $this->printJob->fresh(['printer', 'template', 'variant.item']);

        // Guard: job may have been cancelled while waiting in queue
        if ($job->status === PrintJob::STATUS_CANCELLED) {
            Log::info("ProcessPrintJob: skipped cancelled job [{$job->job_uuid}]");
            return;
        }

        // Mark as processing
        $job->update(['status' => PrintJob::STATUS_PROCESSING]);

        try {
            // Retrieve raw ZPL from persisted payload
            $payload = $job->payload_json['zpl'] ?? '';

            if (empty($payload)) {
                throw new \RuntimeException('Print payload is empty. Template rendering may have failed.');
            }

            // Send to physical printer via TCP
            $printerRepo->sendRawPayload($job->printer, $payload);

            // Mark completed
            $job->markCompleted();

            // Write audit log
            $logRepo->recordPrint($job);

            Log::info("ProcessPrintJob: completed [{$job->job_uuid}]", [
                'printer' => $job->printer->printer_code,
                'copies'  => $job->copies,
            ]);
        } catch (\Throwable $e) {
            // Mark failed and write audit
            $job->markFailed($e->getMessage());
            $logRepo->recordFailed($job, $e->getMessage());

            Log::error("ProcessPrintJob: failed [{$job->job_uuid}]", [
                'error'   => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw so Laravel's queue can handle retries
            throw $e;
        }
    }

    // ─── Lifecycle Hooks ─────────────────────────────────────────────────────────

    /**
     * Called when the job has exceeded all retries.
     */
    public function failed(\Throwable $exception): void
    {
        $this->printJob->update([
            'status'        => PrintJob::STATUS_FAILED,
            'failed_at'     => now(),
            'error_message' => 'Permanently failed after ' . $this->tries . ' attempts: ' . $exception->getMessage(),
        ]);

        Log::critical("ProcessPrintJob: permanently failed [{$this->printJob->job_uuid}]", [
            'error' => $exception->getMessage(),
        ]);
    }
}
