<?php

namespace App\Services\Barcode\Renderers;

class HtmlRenderer implements LabelRendererInterface
{
    public function render(array $data, string $templateType): string
    {
        return match ($templateType) {
            'ITEM_LABEL' => $this->renderItemLabel($data),
            'BIN_LABEL' => $this->renderBinLabel($data),
            default => throw new \InvalidArgumentException("Unsupported template type: {$templateType}"),
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

    private function validateData(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field for HTML rendering: {$field}");
            }
        }
    }
}
