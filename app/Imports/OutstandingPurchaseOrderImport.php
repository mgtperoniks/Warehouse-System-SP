<?php

namespace App\Imports;

use App\Services\OutstandingPurchase\ImportPipelineService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class OutstandingPurchaseOrderImport implements ToCollection, WithHeadingRow
{
    protected $pipeline;
    protected $importResults = [];

    public function __construct()
    {
        $this->pipeline = new ImportPipelineService();
    }

    /**
     * Parse Excel collection and pass it to the import pipeline.
     */
    public function collection(Collection $rows)
    {
        $normalizedRows = [];

        foreach ($rows as $row) {
            $rowData = $row->toArray();

            // Resolve common column variations to normalized keys
            $poNumber = $this->resolveKeys($rowData, ['po_number', 'po_no', 'no_po', 'purchase_order', 'ponumber']);
            $supplierName = $this->resolveKeys($rowData, ['supplier_name', 'supplier', 'vendor_name', 'vendor']);
            $supplierCode = $this->resolveKeys($rowData, ['supplier_code', 'vendor_code']);
            $poDate = $this->resolveKeys($rowData, ['po_date', 'date', 'tanggal_po', 'tanggal']);
            $expectedDate = $this->resolveKeys($rowData, ['expected_date', 'delivery_date', 'expected_delivery_date']);
            $erpCode = $this->resolveKeys($rowData, ['erp_code', 'item_code', 'kode_barang']);
            $itemName = $this->resolveKeys($rowData, ['item_name', 'product_name', 'nama_barang']);
            $orderedQty = $this->resolveKeys($rowData, ['ordered_qty', 'qty', 'quantity', 'ordered_quantity', 'qty_ordered']);
            $unit = $this->resolveKeys($rowData, ['unit', 'uom']) ?: 'PCS';
            $lineNumber = $this->resolveKeys($rowData, ['line_number', 'line_no', 'no_baris', 'no']);
            $remarks = $this->resolveKeys($rowData, ['remarks', 'note', 'notes', 'keterangan']);

            if (empty($poNumber) && empty($erpCode)) {
                continue;
            }

            $normalizedRows[] = [
                'po_number' => $poNumber,
                'supplier_name' => $supplierName,
                'supplier_code' => $supplierCode,
                'po_date' => $poDate,
                'expected_date' => $expectedDate,
                'erp_code' => $erpCode,
                'item_name' => $itemName,
                'ordered_qty' => $orderedQty,
                'unit' => $unit,
                'line_number' => $lineNumber,
                'remarks' => $remarks,
            ];
        }

        $this->importResults = $this->pipeline->process($normalizedRows, 'ERP_IMPORT');
    }

    /**
     * Get the results of the import process.
     */
    public function getResults(): array
    {
        return $this->importResults;
    }

    /**
     * Helper to resolve keys with variation search.
     */
    private function resolveKeys(array $row, array $variants)
    {
        foreach ($variants as $variant) {
            // Check direct match
            if (array_key_exists($variant, $row)) {
                return $row[$variant];
            }
            // Check normalized match (trim and lowercase)
            foreach ($row as $key => $val) {
                $normKey = strtolower(str_replace([' ', '_', '-'], '', $key));
                $normVariant = strtolower(str_replace([' ', '_', '-'], '', $variant));
                if ($normKey === $normVariant) {
                    return $val;
                }
            }
        }
        return null;
    }
}
