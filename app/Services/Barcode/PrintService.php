<?php

namespace App\Services\Barcode;

use App\Services\Barcode\Renderers\TsplRenderer;
use App\Services\Barcode\Renderers\HtmlRenderer;
use Picqer\Barcode\BarcodeGeneratorSVG;

class PrintService
{
    private const DEFAULT_TSC_PRINTER_NAME = 'TSC TE244';

    public function __construct(
        private readonly TsplRenderer $tsplRenderer,
        private readonly HtmlRenderer $htmlRenderer,
        private readonly PrintJobService $printJobService
    ) {}

    /**
     * Print the label based on printer type.
     * 
     * @param array $data Data for the label
     * @param string $labelVariant Label variant (ITEM_LABEL | BIN_LABEL_80X50 | BIN_LABEL_A5 | BIN_LABEL_A4)
     * @param string $printerType TSC | EPSON
     * @param int $copies Number of copies
     * @return mixed Boolean true for TSC (queued), HTML string for EPSON
     */
    public function print(
        array $data,
        string $labelVariant,
        string $printerType,
        int $copies = 1
    ): mixed {
        $printerType = strtoupper(trim($printerType));
        $this->validatePrinterType($printerType);

        // Ensure barcode SVG is generated for any renderer that might need it
        if (!isset($data['barcode_svg']) && isset($data['barcode'])) {
            $data['barcode_svg'] = $this->generateBarcodeSvg($data['barcode']);
        }

        if ($printerType === 'TSC') {
            $payload = $this->tsplRenderer->render($data, $labelVariant);
            
            // Inject copies into TSPL (Replace default PRINT 1,1)
            $payload = str_replace("PRINT 1,1", "PRINT $copies,1", $payload);

            if (empty(trim($payload))) {
                throw new \Exception("Rendered payload is empty. Aborting print.");
            }

            $this->printJobService->createTscJob($payload, self::DEFAULT_TSC_PRINTER_NAME, $labelVariant, $copies);
            return "TSC Print job queued for " . self::DEFAULT_TSC_PRINTER_NAME;
        }

        if ($printerType === 'EPSON') {
            $singleLabel = $this->htmlRenderer->render($data, $labelVariant);
            
            if (in_array($labelVariant, ['BIN_LABEL_A5', 'BIN_LABEL_A4'])) {
                $output = '';
                $pageSizeStyle = $labelVariant === 'BIN_LABEL_A5' ? 'A4 portrait' : 'A4 landscape';
                $containerWidth = $labelVariant === 'BIN_LABEL_A5' ? '194mm' : '281mm';
                $containerHeight = $labelVariant === 'BIN_LABEL_A5' ? '281mm' : '194mm';

                for ($i = 0; $i < $copies; $i++) {
                    $output .= '<div class="page-break" style="width: ' . $containerWidth . '; height: ' . $containerHeight . '; box-sizing: border-box; overflow: hidden;">' . $singleLabel . '</div>';
                }

                return <<<HTML
<div style="background: white; margin: 0; padding: 0;">
    <style>
        @media print {
            @page { size: $pageSizeStyle; margin: 8mm; }
            body { margin: 0; padding: 0; background: white; }
            .page-break { page-break-after: always; break-after: page; page-break-inside: avoid; }
            .page-break:last-child { page-break-after: avoid; break-after: avoid; }
        }
        body { margin: 0; padding: 0; }
    </style>
    $output
</div>
HTML;
            }

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
    public function renderPreview(array $data, string $labelVariant): string
    {
        if (!isset($data['barcode_svg']) && isset($data['barcode'])) {
            $data['barcode_svg'] = $this->generateBarcodeSvg($data['barcode']);
        }

        return $this->htmlRenderer->render($data, $labelVariant);
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



    private function validatePrinterType(string $type): void
    {
        $allowed = ['TSC', 'EPSON'];
        if (!in_array($type, $allowed)) {
            throw new \InvalidArgumentException("Unsupported printer type: {$type}.");
        }
    }
}
