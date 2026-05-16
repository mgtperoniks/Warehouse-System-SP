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

        // Use 24 character limit for Font "2" to ensure zero horizontal bleeding
        $nameLines = $this->formatItemName($data['item_name'], 24);
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
        $cmds[] = "SET TEAR ON";
        
        // --- Header Zone (Deterministic 2-Line Name) ---
        // Line 1 fixed at Y=16
        $cmds[] = "TEXT 10,16,\"" . self::FONT_TITLE . "\",0,1,1,\"" . $nameLines['line1'] . "\"";
        
        $erpY = 68; // ERP position if only 1 line exists
        if (!empty($nameLines['line2'])) {
            // Line 2 fixed at Y=40
            $cmds[] = "TEXT 10,40,\"" . self::FONT_TITLE . "\",0,1,1,\"" . $nameLines['line2'] . "\"";
            $erpY = 92; // Push ERP down to Y=92 if 2 lines exist
        }

        // ERP rendering (Restore FONT_TITLE for high-visibility hierarchy)
        $cmds[] = "TEXT 10,$erpY,\"" . self::FONT_TITLE . "\",0,1,1,\"ERP: $erp\"";
        
        // --- Barcode Zone (Restored Big Barcode) ---
        // Width multiplier 3 for bold industrial dominance at Y=128
        $cmds[] = "BARCODE 35,128,\"128\",50,0,0,3,3,\"$barcode\"";
        
        // --- Human Readable Barcode Text (Y=182) ---
        $barcodeTextX = max(120, 220 - (mb_strlen($barcode) * 8));
        $cmds[] = "TEXT $barcodeTextX,182,\"" . self::FONT_TITLE . "\",0,1,1,2,\"$barcode\"";
        
        // --- Footer Zone (Y=225) ---
        $cmds[] = "TEXT 10,225,\"" . self::FONT_NORMAL . "\",0,1,1,\"Last In: $lastIn\"";
        $cmds[] = "TEXT 390,225,\"" . self::FONT_NORMAL . "\",0,1,1,3,\"Bin: $bin\"";
        
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

    /**
     * Formats item name for deterministic 2-line rendering.
     * Returns array with line1 and line2.
     */
    private function formatItemName(string $name, int $limitPerLine = 24): array
    {
        $name = strtoupper($this->sanitize($name));
        $words = explode(' ', $name);
        
        $lines = ['line1' => '', 'line2' => ''];
        $currentLine = 'line1';

        foreach ($words as $word) {
            if (empty($word)) continue;

            $space = $lines[$currentLine] === '' ? '' : ' ';
            $proposed = $lines[$currentLine] . $space . $word;
            
            if (mb_strlen($proposed) <= $limitPerLine) {
                $lines[$currentLine] = $proposed;
            } else {
                if ($currentLine === 'line1') {
                    $currentLine = 'line2';
                    if (mb_strlen($word) > $limitPerLine) {
                        $lines[$currentLine] = mb_substr($word, 0, $limitPerLine - 3) . '...';
                        break;
                    }
                    $lines[$currentLine] = $word;
                } else {
                    if (mb_strlen($lines['line2']) > $limitPerLine - 3) {
                        $lines['line2'] = mb_substr($lines['line2'], 0, $limitPerLine - 3) . '...';
                    } else {
                        $lines['line2'] .= '...';
                    }
                    break;
                }
            }
        }

        return $lines;
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
