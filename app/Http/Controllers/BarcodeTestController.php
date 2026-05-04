<?php

namespace App\Http\Controllers;

use App\Services\Barcode\Renderers\HtmlRenderer;
use Illuminate\Http\Request;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeTestController extends Controller
{
    public function __construct(
        private readonly HtmlRenderer $htmlRenderer
    ) {}

    public function index(Request $request)
    {
        $type = $request->query('type', 'item');
        
        if (!in_array($type, ['item', 'bin'])) {
            abort(400, 'Invalid label type. Allowed: item, bin');
        }

        $barcodeValue = $request->query('barcode', '123456789');
        
        $generator = new BarcodeGeneratorSVG();
        $barcodeSvg = 'data:image/svg+xml;base64,' . base64_encode(
            $generator->getBarcode($barcodeValue, $generator::TYPE_CODE_128, 2, 40)
        );

        if ($type === 'bin') {
            $data = [
                'item_name' => 'TEST BOLT M8X20',
                'erp_code' => 'ERP001',
                'barcode' => $barcodeValue,
                'bin_code' => 'A-01',
                'barcode_svg' => $barcodeSvg
            ];
            $templateType = 'BIN_LABEL';
            $labelCount = 2 * 5; // 2 columns x 5 rows
        } else {
            $data = [
                'item_name' => 'TEST BOLT M8X20',
                'erp_code' => 'ERP001',
                'barcode' => $barcodeValue,
                'last_stock_in_date' => now()->format('Y-m-d'),
                'barcode_svg' => $barcodeSvg
            ];
            $templateType = 'ITEM_LABEL';
            $labelCount = 6 * 5; // 6 columns x 5 rows
        }

        $renderedLabel = $this->htmlRenderer->render($data, $templateType);

        $labels = array_fill(0, $labelCount, $renderedLabel);

        return view('barcode.test-print', compact('labels', 'type'));
    }
}
