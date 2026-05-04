<?php

namespace App\Services\Barcode;

use App\Services\Barcode\Renderers\TsplRenderer;
use App\Services\Barcode\Renderers\HtmlRenderer;
use Picqer\Barcode\BarcodeGeneratorSVG;

class PrintService
{
    private const PRINTER_PORT = 9100;
    private const SOCKET_TIMEOUT = 3;

    public function __construct(
        private readonly TsplRenderer $tsplRenderer,
        private readonly HtmlRenderer $htmlRenderer
    ) {}

    /**
     * Print the label based on printer type.
     * 
     * @param array $data Data for the label
     * @param string $templateType ITEM_LABEL | BIN_LABEL
     * @param string $printerType TSC | EPSON
     * @param string|null $printerIp Required if printerType is TSC
     * @param int $copies Number of copies
     * @return mixed Boolean true for TSC (direct socket), HTML string for EPSON
     */
    public function print(
        array $data,
        string $templateType,
        string $printerType,
        ?string $printerIp = null,
        int $copies = 1
    ): mixed {
        $printerType = strtoupper(trim($printerType));
        $this->validatePrinterType($printerType);

        // Ensure barcode SVG is generated for any renderer that might need it
        if (!isset($data['barcode_svg']) && isset($data['barcode'])) {
            $data['barcode_svg'] = $this->generateBarcodeSvg($data['barcode']);
        }

        if ($printerType === 'TSC') {
            if (empty($printerIp)) {
                throw new \InvalidArgumentException("Printer IP is required for TSC printers.");
            }

            $payload = $this->tsplRenderer->render($data, $templateType);
            
            // Inject copies into TSPL (Replace default PRINT 1,1)
            $payload = str_replace("PRINT 1,1", "PRINT $copies,1", $payload);

            if (empty(trim($payload))) {
                throw new \Exception("Rendered payload is empty. Aborting print.");
            }

            return $this->sendToTsc($printerIp, $payload);
        }

        if ($printerType === 'EPSON') {
            $singleLabel = $this->htmlRenderer->render($data, $templateType);
            
            // Duplicate HTML for Epson (A4 stickers)
            $output = '';
            for ($i = 0; $i < $copies; $i++) {
                $output .= '<div style="display: inline-block; margin: 2mm; vertical-align: top;">' . $singleLabel . '</div>';
            }

            // Wrap in print-safe container
            return <<<HTML
<div style="width: 210mm; min-height: 297mm; padding: 10mm; box-sizing: border-box; background: white;">
    <style>
        @media print { @page { size: A4; margin: 0; } body { margin: 0; } }
    </style>
    $output
</div>
HTML;
        }

        return false;
    }

    /**
     * Render a single label for preview purposes.
     */
    public function renderPreview(array $data, string $templateType): string
    {
        if (!isset($data['barcode_svg']) && isset($data['barcode'])) {
            $data['barcode_svg'] = $this->generateBarcodeSvg($data['barcode']);
        }

        return $this->htmlRenderer->render($data, $templateType);
    }

    /**
     * Generate Code128 SVG barcode.
     */
    public function generateBarcodeSvg(string $value): string
    {
        $generator = new BarcodeGeneratorSVG();
        return 'data:image/svg+xml;base64,' . base64_encode(
            $generator->getBarcode($value, $generator::TYPE_CODE_128, 2, 40)
        );
    }

    /**
     * Send raw payload to TSC printer via TCP socket.
     */
    private function sendToTsc(string $ip, string $payload): bool
    {
        $socket = @fsockopen($ip, self::PRINTER_PORT, $errno, $errstr, self::SOCKET_TIMEOUT);

        if (!$socket) {
            throw new \Exception("Could not connect to printer at $ip:" . self::PRINTER_PORT . ". Error: $errstr ($errno)");
        }

        stream_set_timeout($socket, self::SOCKET_TIMEOUT);

        try {
            $totalBytes = strlen($payload);
            $written = 0;

            while ($written < $totalBytes) {
                $result = fwrite($socket, substr($payload, $written));
                
                if ($result === false) {
                    throw new \Exception("Failed to write to socket after $written bytes.");
                }
                
                $written += $result;
            }

            fflush($socket);
        } finally {
            fclose($socket);
        }

        return true;
    }

    private function validatePrinterType(string $type): void
    {
        $allowed = ['TSC', 'EPSON'];
        if (!in_array($type, $allowed)) {
            throw new \InvalidArgumentException("Unsupported printer type: {$type}.");
        }
    }
}
