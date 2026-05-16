<?php

namespace App\Services\Barcode;

use App\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PrintJobService
{
    /**
     * Create a new TSC print job.
     */
    public function createTscJob(string $payload, string $printerName, string $templateType, int $copies = 1): PrintJob
    {
        \Log::info('[PRINT_TRIGGER] createTscJob Initiated', [
            'user' => Auth::user()?->name ?? 'Guest',
            'user_id' => Auth::id(),
            'route' => \Route::currentRouteName(),
            'url' => request()->fullUrl(),
            'ip' => request()->ip(),
            'method' => request()->method(),
            'payload_keys' => array_keys(request()->all()),
            'agent' => request()->userAgent(),
            'backtrace' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6))
                ->map(fn($t) => ($t['class'] ?? '') . '@' . ($t['function'] ?? ''))
                ->toArray()
        ]);

        \Log::info('>>> [BEFORE] createTscJob', ['template' => $templateType]);

        $job = PrintJob::create([
            'printer_name' => $printerName,
            'payload_tspl' => $payload,
            'payload_hash' => hash('sha256', $payload),
            'copies' => $copies,
            'status' => 'pending',
            'template_type' => $templateType,
            'created_by' => Auth::id(),
        ]);

        \Log::info('<<< [AFTER] createTscJob', [
            'id' => $job->id,
            'payload_hash' => $job->payload_hash
        ]);

        return $job;
    }

    /**
     * Atomic claim for the next available job.
     * Only allows jobs newer than 10 minutes to prevent reprinting of stale historical jobs.
     */
    public function claimNextJob(string $machineId, string $printerName): ?PrintJob
    {
        $tenMinutesAgo = now()->subMinutes(10);

        \Log::info('>>> [BEFORE] claimNextJob', [
            'machine' => $machineId, 
            'printer' => $printerName,
            'threshold' => $tenMinutesAgo->toDateTimeString()
        ]);

        return DB::transaction(function () use ($machineId, $printerName, $tenMinutesAgo) {
            $job = PrintJob::where('status', 'pending')
                ->where('printer_name', $printerName)
                ->where('created_at', '>=', $tenMinutesAgo)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->first();

            if ($job) {
                $job->update([
                    'status' => 'processing',
                    'claimed_by_machine' => $machineId,
                    'claimed_at' => now(),
                ]);
            } else {
                // Check if there are ANY stale pending jobs for logging/debugging
                $staleCount = PrintJob::where('status', 'pending')
                    ->where('printer_name', $printerName)
                    ->where('created_at', '<', $tenMinutesAgo)
                    ->count();
                
                if ($staleCount > 0) {
                    \Log::warning("[QUEUE] Ignoring $staleCount stale pending jobs (older than 10 mins)");
                }
            }

            \Log::info('<<< [AFTER] claimNextJob', ['job_id' => $job?->id]);
            return $job;
        });
    }

    /**
     * Recover jobs stuck in 'processing' for more than 5 minutes.
     */
    public function recoverStaleJobs(): int
    {
        $count = PrintJob::where('status', 'processing')
            ->where('claimed_at', '<', now()->subMinutes(5))
            ->update([
                'status' => 'pending',
                'claimed_by_machine' => null,
                'claimed_at' => null,
            ]);

        if ($count > 0) {
            \Log::info("[QUEUE] Recovery completed: Reset $count stale processing jobs to pending");
        }

        return $count;
    }

    /**
     * Get queue statistics.
     */
    public function getStats(): array
    {
        return [
            'pending' => PrintJob::where('status', 'pending')->count(),
            'processing' => PrintJob::where('status', 'processing')->count(),
            'printed_today' => PrintJob::where('status', 'printed')->whereDate('printed_at', today())->count(),
            'failed' => PrintJob::where('status', 'failed')->count(),
        ];
    }

    /**
     * Clear all pending jobs (Administrator manually).
     */
    public function clearAllPending(): int
    {
        $count = PrintJob::where('status', 'pending')->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => 'Cleared manually by administrator (Queue Hygiene)',
        ]);

        if ($count > 0) {
            \Log::info("[QUEUE] Cleared $count pending jobs");
        }

        return $count;
    }

    public function markPrinted(int $id): bool
    {
        return PrintJob::where('id', $id)->update([
            'status' => 'printed',
            'printed_at' => now(),
        ]);
    }

    public function markFailed(int $id, string $error): bool
    {
        return PrintJob::where('id', $id)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $error,
        ]);
    }
}
