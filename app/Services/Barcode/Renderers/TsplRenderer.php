<?php

namespace App\Services\Barcode\Renderers;

class TsplRenderer implements LabelRendererInterface
{
    private const FONT_TITLE = '2';
    private const FONT_NORMAL = '1';

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
        $this->validateData($data, ['item_name', 'erp_code', 'barcode', 'last_stock_in_date']);

        $name = $this->sanitize(strtoupper($data['item_name']));
        $erp = $this->sanitize($data['erp_code']);
        $barcode = $this->sanitize($data['barcode']);
        $lastIn = $this->sanitize($data['last_stock_in_date']);
        $bin = $this->sanitize($data['bin_code'] ?? '-');

        $cmds = [];
        $cmds[] = "SIZE 50 mm, 30 mm";
        $cmds[] = "GAP 3 mm, 0";
        $cmds[] = "DIRECTION 1,0";
        $cmds[] = "REFERENCE 0,0";
        $cmds[] = "OFFSET 0 mm";
        $cmds[] = "CLS";
        
        // --- Header Zone (45% = 108 dots) ---
        $cmds[] = "BLOCK 10,10,380,55,\"" . self::FONT_TITLE . "\",0,1,1,2,0,\"$name\"";
        $cmds[] = "TEXT 10,75,\"" . self::FONT_NORMAL . "\",0,1,1,\"ERP: $erp\"";
        
        // --- Barcode Zone (30% = 72 dots) ---
        $cmds[] = "BARCODE 40,120,\"128\",50,0,0,2,2,\"$barcode\"";
        
        // --- Footer Zone (25% = 60 dots) ---
        // Barcode human-readable centered
        $cmds[] = "TEXT 200,195,\"" . self::FONT_NORMAL . "\",0,1,1,2,\"$barcode\"";
        // Split Footer: Last In (Left) and Bin (Right)
        $cmds[] = "TEXT 10,215,\"" . self::FONT_NORMAL . "\",0,1,1,\"Last In: $lastIn\"";
        $cmds[] = "TEXT 390,215,\"" . self::FONT_NORMAL . "\",0,1,1,3,\"Bin: $bin\"";
        
        $cmds[] = "PRINT 1,1";

        return implode("\r\n", $cmds) . "\r\n";
    }

    private function renderBinLabel(array $data): string
    {
        $this->validateData($data, ['item_name', 'erp_code', 'barcode', 'bin_code']);

        $name = $this->sanitize(strtoupper($data['item_name']));
        $erp = $this->sanitize($data['erp_code']);
        $barcode = $this->sanitize($data['barcode']);
        $binCode = $this->sanitize($data['bin_code']);

        $cmds = [];
        $cmds[] = "SIZE 80 mm, 50 mm";
        $cmds[] = "GAP 3 mm, 0";
        $cmds[] = "DIRECTION 1,0";
        $cmds[] = "REFERENCE 0,0";
        $cmds[] = "OFFSET 0 mm";
        $cmds[] = "CLS";

        // --- Header Zone (30% = 120 dots) - Hybrid Split ---
        $cmds[] = "BLOCK 30,15,410,65,\"" . self::FONT_TITLE . "\",0,1,1,2,0,\"$name\"";
        $cmds[] = "TEXT 30,85,\"" . self::FONT_NORMAL . "\",0,1,1,\"ERP: $erp\"";
        
        $cmds[] = "BOX 460,15,620,105,4";
        $cmds[] = "TEXT 540,60,\"" . self::FONT_TITLE . "\",0,2,2,2,\"$binCode\"";
        
        // --- Barcode Zone (50% = 200 dots) ---
        $cmds[] = "BARCODE 20,150,\"128\",160,0,0,2,4,\"$barcode\"";
        
        // --- Footer Zone (20% = 80 dots) ---
        $cmds[] = "TEXT 320,350,\"" . self::FONT_TITLE . "\",0,1,1,2,\"$barcode\"";
        
        $cmds[] = "PRINT 1,1";

        return implode("\r\n", $cmds) . "\r\n";
    }

    private function sanitize(string $value): string
    {
        $value = str_replace(['"', "\r", "\n"], '', $value);
        return preg_replace('/[^\x20-\x7E]/', '', $value);
    }

    private function validateData(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field for TSPL rendering: {$field}");
            }
        }
    }
}
