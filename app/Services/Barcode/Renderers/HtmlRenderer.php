<?php

namespace App\Services\Barcode\Renderers;

class HtmlRenderer implements LabelRendererInterface
{
    public function render(array $data, string $labelVariant): string
    {
        return match ($labelVariant) {
            'ITEM_LABEL' => $this->renderItemLabel($data),
            'BIN_LABEL', 'BIN_LABEL_80X50' => $this->renderBinLabel($data),
            'BIN_LABEL_A5' => $this->renderBinLabelA5($data),
            'BIN_LABEL_A4' => $this->renderBinLabelA4($data),
            default => throw new \InvalidArgumentException("Unsupported label variant: {$labelVariant}"),
        };
    }

    private function renderItemLabel(array $data): string
    {
        $this->validateData($data, ['item_name', 'erp_code', 'barcode', 'last_stock_in_date', 'barcode_svg']);

        $name = htmlspecialchars(substr($data['item_name'], 0, 48));
        $erp = htmlspecialchars($data['erp_code']);
        $barcode = htmlspecialchars($data['barcode']);
        $lastIn = htmlspecialchars($data['last_stock_in_date']);
        $bin = htmlspecialchars($data['bin_code'] ?? '-');
        $barcodeImg = $data['barcode_svg']; 

        return <<<HTML
<div style="width: 50mm; height: 30mm; padding: 0; box-sizing: border-box; border: 1px solid #ddd; font-family: sans-serif; display: flex; flex-direction: column; background: white; overflow: hidden;">
    <!-- Header Zone (45% = 13.5mm) -->
    <div style="height: 45%; padding: 1.5mm 2mm; box-sizing: border-box; overflow: hidden;">
        <div style="font-size: 9pt; font-weight: bold; line-height: 1.1; overflow: hidden; height: 6.5mm; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">$name</div>
        <div style="font-size: 7.5pt; color: #444; margin-top: 1mm;">ERP: $erp</div>
    </div>
    
    <!-- Barcode Zone (30% = 9mm) -->
    <div style="height: 30%; display: flex; align-items: center; justify-content: center; padding: 0 2mm; box-sizing: border-box;">
        <img src="$barcodeImg" style="width: 80%; height: 7mm; display: block;">
    </div>
    
    <!-- Footer Zone (25% = 7.5mm) -->
    <div style="height: 25%; padding: 0 2mm 1mm 2mm; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between;">
        <div style="font-size: 7.5pt; font-family: monospace; text-align: center; line-height: 1;">$barcode</div>
        <div style="font-size: 6.5pt; color: #777; border-top: 0.5px solid #eee; padding-top: 0.5mm; display: flex; justify-content: space-between;">
            <span>Last In: $lastIn</span>
            <span>Bin: $bin</span>
        </div>
    </div>
</div>
HTML;
    }

    private function renderBinLabel(array $data): string
    {
        $this->validateData($data, ['item_name', 'erp_code', 'barcode', 'bin_code', 'barcode_svg']);

        $name = htmlspecialchars($data['item_name']);
        $erp = htmlspecialchars($data['erp_code']);
        $barcode = htmlspecialchars($data['barcode']);
        $binCode = htmlspecialchars($data['bin_code']);
        $barcodeImg = $data['barcode_svg'];

        return <<<HTML
<div style="width: 80mm; height: 50mm; padding: 0; box-sizing: border-box; border: 1px solid #ddd; font-family: sans-serif; display: flex; flex-direction: column; background: white; overflow: hidden;">
    <!-- Header Zone (30% = 15mm) - Hybrid Split -->
    <div style="height: 30%; padding: 2mm 4mm; box-sizing: border-box; display: flex; justify-content: space-between; align-items: center; overflow: hidden;">
        <!-- Left Section (70%) -->
        <div style="width: 70%; display: flex; flex-direction: column; justify-content: center;">
            <div style="font-size: 11pt; font-weight: bold; line-height: 1.1; overflow: hidden; height: 8.5mm; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">$name</div>
            <div style="font-size: 9pt; color: #555; margin-top: 0.5mm;">ERP: $erp</div>
        </div>
        <!-- Right Section (30%): Bin Code Box -->
        <div style="width: 25%; border: 2.5px solid black; padding: 1mm; box-sizing: border-box; text-align: center; background: white;">
            <div style="font-size: 16pt; font-weight: 900; line-height: 1; word-break: break-all;">$binCode</div>
        </div>
    </div>
    
    <!-- Barcode Zone (50% = 25mm) -->
    <div style="height: 50%; display: flex; align-items: center; justify-content: center; padding: 0 4mm; box-sizing: border-box;">
        <img src="$barcodeImg" style="width: 100%; height: 20mm; display: block;">
    </div>
    
    <!-- Footer Zone (20% = 10mm) -->
    <div style="height: 20%; padding: 0 4mm 2mm 4mm; box-sizing: border-box; display: flex; align-items: center; justify-content: center;">
        <div style="font-size: 14pt; font-family: monospace; text-align: center; letter-spacing: 4px; font-weight: bold; line-height: 1;">$barcode</div>
    </div>
</div>
HTML;
    }

    private function renderBinLabelA5(array $data): string
    {
        $this->validateData($data, ['item_name', 'erp_code', 'barcode', 'bin_code', 'barcode_svg']);

        $name = htmlspecialchars(strtoupper($data['item_name']));
        $erp = htmlspecialchars($data['erp_code']);
        $barcode = htmlspecialchars($data['barcode']);
        $binCode = htmlspecialchars(strtoupper($data['bin_code']));
        $barcodeImg = $data['barcode_svg'];

        return <<<HTML
<div style="width: 194mm; height: 140mm; padding: 0; box-sizing: border-box; font-family: Arial, Helvetica, 'Segoe UI', sans-serif; display: flex; flex-direction: column; background: white; overflow: hidden; color: black; border: 2mm solid #000;">
    <!-- Upper Section: Rack / Bin Code (28% height) -->
    <div style="height: 28%; border-bottom: 2mm solid #000; display: flex; align-items: center; justify-content: center; box-sizing: border-box; overflow: hidden; padding: 0 2mm;">
        <div style="font-size: 64pt; font-weight: 900; line-height: 1; text-align: center; width: 100%; white-space: nowrap; letter-spacing: -1px;">$binCode</div>
    </div>

    <!-- Middle Section: Barcode (42% height) -->
    <div style="height: 42%; border-bottom: 2mm solid #000; display: flex; flex-direction: column; align-items: center; justify-content: center; box-sizing: border-box; padding: 1mm 0;">
        <img src="$barcodeImg" style="width: 92%; height: 30mm; display: block; object-fit: fill;">
        <div style="font-size: 16pt; font-family: monospace; font-weight: bold; letter-spacing: 4px; margin-top: 1mm; line-height: 1; text-align: center;">$barcode</div>
    </div>

    <!-- Lower Section: Item Name & ERP Code (30% height) -->
    <div style="height: 30%; display: flex; flex-direction: column; justify-content: center; align-items: center; box-sizing: border-box; padding: 1mm 2mm; overflow: hidden;">
        <div style="font-size: 22pt; font-weight: bold; text-align: center; line-height: 1.15; width: 100%; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-transform: uppercase;">$name</div>
        <div style="font-size: 15pt; font-family: monospace; font-weight: 550; color: #000; text-align: center; margin-top: 1mm; line-height: 1;">ERP: $erp</div>
    </div>
</div>
HTML;
    }

    private function renderBinLabelA4(array $data): string
    {
        $this->validateData($data, ['item_name', 'erp_code', 'barcode', 'bin_code', 'barcode_svg']);

        $name = htmlspecialchars(strtoupper($data['item_name']));
        $erp = htmlspecialchars($data['erp_code']);
        $barcode = htmlspecialchars($data['barcode']);
        $binCode = htmlspecialchars(strtoupper($data['bin_code']));
        $barcodeImg = $data['barcode_svg'];

        return <<<HTML
<div style="width: 281mm; height: 194mm; padding: 0; box-sizing: border-box; font-family: Arial, Helvetica, 'Segoe UI', sans-serif; display: flex; flex-direction: column; background: white; overflow: hidden; color: black; border: 4px solid #000;">
    <!-- Upper Section: Rack / Bin Code (28% height) -->
    <div style="height: 28%; border-bottom: 4mm solid #000; display: flex; align-items: center; justify-content: center; box-sizing: border-box; overflow: hidden; padding: 0 4mm;">
        <div style="font-size: 115pt; font-weight: 900; line-height: 1; text-align: center; width: 100%; white-space: nowrap; letter-spacing: -2px;">$binCode</div>
    </div>

    <!-- Middle Section: Barcode (42% height) -->
    <div style="height: 42%; border-bottom: 4mm solid #000; display: flex; flex-direction: column; align-items: center; justify-content: center; box-sizing: border-box; padding: 2mm 0;">
        <img src="$barcodeImg" style="width: 90%; height: 44mm; display: block; object-fit: fill;">
        <div style="font-size: 24pt; font-family: monospace; font-weight: bold; letter-spacing: 8px; margin-top: 2mm; line-height: 1; text-align: center;">$barcode</div>
    </div>

    <!-- Lower Section: Item Name & ERP Code (30% height) -->
    <div style="height: 30%; display: flex; flex-direction: column; justify-content: center; align-items: center; box-sizing: border-box; padding: 2mm 4mm; overflow: hidden;">
        <div style="font-size: 30pt; font-weight: bold; text-align: center; line-height: 1.15; width: 100%; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-transform: uppercase;">$name</div>
        <div style="font-size: 18pt; font-family: monospace; font-weight: 550; color: #000; text-align: center; margin-top: 1.5mm; line-height: 1;">ERP: $erp</div>
    </div>
</div>
HTML;
    }

    private function validateData(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field for HTML rendering: {$field}");
            }
        }
    }
}
