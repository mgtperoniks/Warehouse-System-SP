<?php

namespace App\Repositories;

use App\Models\Printer;
use Illuminate\Support\Facades\Log;

class PrinterRepository
{
    /**
     * Attempt a TCP connection to the printer to check if it's online.
     * Updates the printer's status and last_seen_at accordingly.
     *
     * @param  Printer  $printer
     * @param  int      $timeoutSeconds
     * @return bool  true = online, false = offline
     */
    public function ping(Printer $printer, int $timeoutSeconds = 3): bool
    {
        if (! $printer->printer_ip) {
            $printer->update(['status' => 'offline']);
            return false;
        }

        $socket = @fsockopen(
            $printer->printer_ip,
            $printer->printer_port,
            $errno,
            $errstr,
            $timeoutSeconds
        );

        $isOnline = $socket !== false;

        if ($isOnline) {
            fclose($socket);
        }

        $printer->update([
            'status'      => $isOnline ? 'online' : 'offline',
            'last_seen_at' => $isOnline ? now() : $printer->last_seen_at,
        ]);

        return $isOnline;
    }

    /**
     * Send a raw ZPL/EPL payload to the printer via TCP socket.
     *
     * @param  Printer  $printer
     * @param  string   $payload  Raw ZPL or EPL string
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function sendRawPayload(Printer $printer, string $payload): bool
    {
        if (! $printer->printer_ip) {
            throw new \RuntimeException("Printer [{$printer->printer_code}] has no IP address configured.");
        }

        $socket = @fsockopen(
            $printer->printer_ip,
            $printer->printer_port,
            $errno,
            $errstr,
            5 // 5-second connect timeout
        );

        if ($socket === false) {
            $printer->update(['status' => 'offline']);
            throw new \RuntimeException(
                "Cannot connect to printer [{$printer->printer_code}] at {$printer->printer_ip}:{$printer->printer_port}. Error {$errno}: {$errstr}"
            );
        }

        try {
            fwrite($socket, $payload);
            fclose($socket);

            $printer->update([
                'status'       => 'online',
                'last_seen_at' => now(),
            ]);

            Log::info('PrinterRepository: payload sent', [
                'printer_code' => $printer->printer_code,
                'payload_size' => strlen($payload),
            ]);

            return true;
        } catch (\Throwable $e) {
            fclose($socket);
            $printer->update(['status' => 'offline']);
            throw new \RuntimeException(
                "Failed to send payload to printer [{$printer->printer_code}]: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get all active printers with their current status.
     */
    public function getActivePrinters()
    {
        return Printer::active()->orderBy('location')->get();
    }
}
