<?php

namespace App\Livewire\OutstandingPurchase;

use App\Imports\OutstandingPurchaseOrderImport;
use App\Services\OutstandingPurchase\ImportPipelineService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class OutstandingPurchaseImportPage extends Component
{
    use WithFileUploads;

    public $excelFile;
    public $importResults = null;
    public $isProcessing = false;

    /**
     * Handle bulk saving from the Handsontable grid.
     */
    public function saveFromHandsontable(array $data)
    {
        $this->isProcessing = true;

        // Map list from Handsontable columns to normalized array keys
        // Columns index:
        // 0: PO Number
        // 1: Supplier Name
        // 2: Supplier Code
        // 3: PO Date
        // 4: Expected Date
        // 5: ERP Code
        // 6: Item Name
        // 7: Ordered Qty
        // 8: Unit
        // 9: Line Number
        // 10: Remarks
        $normalizedRows = [];
        foreach ($data as $row) {
            $normalizedRows[] = [
                'supplier_code' => $row[0] ?? null,
                'supplier_name' => $row[1] ?? null,
                // Index 2 & 3 are reserved blank columns in the ERP sheet
                'po_number' => $row[4] ?? null,
                'po_date' => $row[5] ?? null,
                'erp_code' => $row[6] ?? null,
                'item_name' => $row[7] ?? null,
                'unit' => $row[8] ?? 'PCS',
                'ordered_qty' => $row[9] ?? 0,
                'line_number' => null, // Generated automatically on ingest
                'remarks' => null,
            ];
        }

        try {
            $pipeline = new ImportPipelineService();
            $this->importResults = $pipeline->process($normalizedRows, 'CLIPBOARD');
        } catch (\Exception $e) {
            $this->importResults = [
                'success' => 0,
                'failed' => 1,
                'errors' => [
                    ['row' => '-', 'po_number' => '-', 'erp_code' => '-', 'reason' => $e->getMessage()]
                ]
            ];
        }

        $this->isProcessing = false;
        $this->dispatch('importCompleted', $this->importResults);
    }

    /**
     * Handle Excel upload.
     */
    public function importExcel()
    {
        $this->validate([
            'excelFile' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $this->isProcessing = true;

        try {
            $importInstance = new OutstandingPurchaseOrderImport();
            Excel::import($importInstance, $this->excelFile->getRealPath());
            $this->importResults = $importInstance->getResults();
        } catch (\Exception $e) {
            $this->importResults = [
                'success' => 0,
                'failed' => 1,
                'errors' => [
                    ['row' => '-', 'po_number' => '-', 'erp_code' => '-', 'reason' => $e->getMessage()]
                ]
            ];
        }

        $this->isProcessing = false;
        $this->excelFile = null;
        $this->dispatch('importCompleted', $this->importResults);
    }

    public function render()
    {
        return view('livewire.outstanding-purchase.outstanding-purchase-import-page')
            ->layout('layouts.app');
    }
}
