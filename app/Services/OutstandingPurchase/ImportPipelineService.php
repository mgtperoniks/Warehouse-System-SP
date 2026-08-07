<?php

namespace App\Services\OutstandingPurchase;

use App\Models\OutstandingPurchaseOrder;
use App\Models\OutstandingPurchaseOrderItem;
use App\Models\Supplier;
use App\Models\ItemVariant;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportPipelineService
{
    /**
     * Run the import pipeline on a normalized set of rows.
     *
     * @param array $rows Array of associative arrays representing PO line items.
     * @param string $source The source of the import (e.g., 'ERP_IMPORT', 'CLIPBOARD').
     * @return array Summary of import results.
     */
    public function process(array $rows, string $source = 'ERP_IMPORT'): array
    {
        $warehouseId = session('active_warehouse_id');
        if (!$warehouseId) {
            throw new \Exception("Active warehouse context is missing.");
        }

        $successCount = 0;
        $failedCount = 0;
        $errors = [];
        $poNumbersProcessed = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                // Normalize input keys
                $poNumber = trim($row['po_number'] ?? '');
                $supplierName = trim($row['supplier_name'] ?? '');
                $supplierCode = trim($row['supplier_code'] ?? '');
                $poDateRaw = $row['po_date'] ?? null;
                $expectedDateRaw = $row['expected_date'] ?? null;
                $erpCode = trim($row['erp_code'] ?? '');
                $itemName = trim($row['item_name'] ?? '');
                $orderedQty = $row['ordered_qty'] ?? 0;
                $unit = trim($row['unit'] ?? 'PCS');
                $lineNumber = isset($row['line_number']) ? (int)$row['line_number'] : null;
                $remarks = trim($row['remarks'] ?? '');

                // Validation
                if (empty($poNumber) || empty($erpCode)) {
                    // Skip empty rows silently or log it
                    continue;
                }

                if (empty($supplierName) || empty($itemName) || $orderedQty <= 0) {
                    $failedCount++;
                    $errors[] = [
                        'row' => $index + 1,
                        'po_number' => $poNumber,
                        'erp_code' => $erpCode,
                        'reason' => 'Missing Supplier, Item Name, or Ordered Quantity <= 0'
                    ];
                    continue;
                }

                // Date normalization
                try {
                    $poDate = $poDateRaw ? Carbon::parse($poDateRaw)->format('Y-m-d') : Carbon::now()->format('Y-m-d');
                    $expectedDate = $expectedDateRaw ? Carbon::parse($expectedDateRaw)->format('Y-m-d') : null;
                } catch (\Exception $dateEx) {
                    $failedCount++;
                    $errors[] = [
                        'row' => $index + 1,
                        'po_number' => $poNumber,
                        'erp_code' => $erpCode,
                        'reason' => 'Invalid date format: ' . $dateEx->getMessage()
                    ];
                    continue;
                }

                // 1. Resolve Supplier ID if possible
                $supplier = Supplier::where('name', $supplierName)
                    ->orWhere('phone', $supplierName) // Search fallback
                    ->first();

                // 2. Find or Create Parent PO
                $po = OutstandingPurchaseOrder::where('warehouse_id', $warehouseId)
                    ->where('po_number', $poNumber)
                    ->first();

                if ($po) {
                    $po->update([
                        'supplier_id' => $supplier ? $supplier->id : $po->supplier_id,
                        'supplier_name_snapshot' => $supplierName,
                        'supplier_code_snapshot' => !empty($supplierCode) ? $supplierCode : $po->supplier_code_snapshot,
                        'expected_date' => $expectedDate ?: $po->expected_date,
                        'imported_at' => now(),
                    ]);
                } else {
                    $po = OutstandingPurchaseOrder::create([
                        'warehouse_id' => $warehouseId,
                        'supplier_id' => $supplier ? $supplier->id : null,
                        'supplier_name_snapshot' => $supplierName,
                        'supplier_code_snapshot' => $supplierCode ?: null,
                        'po_number' => $poNumber,
                        'po_date' => $poDate,
                        'expected_date' => $expectedDate,
                        'status' => OutstandingPurchaseOrder::STATUS_PENDING,
                        'is_archived' => false,
                        'source' => $source,
                        'remarks' => $remarks ?: null,
                        'imported_at' => now(),
                    ]);
                }

                $poNumbersProcessed[] = $poNumber;

                // 3. Resolve Item Variant ID
                $variant = ItemVariant::resolveVariant($erpCode, $itemName);

                // 4. Find or Create PO Line Item
                $poItem = OutstandingPurchaseOrderItem::where('outstanding_purchase_order_id', $po->id)
                    ->where('erp_code', $erpCode)
                    ->first();

                if ($poItem) {
                    $poItem->update([
                        'item_variant_id' => $variant ? $variant->id : $poItem->item_variant_id,
                        'item_name_snapshot' => $itemName,
                        'ordered_qty' => (int)$orderedQty,
                        'remarks' => $remarks ?: $poItem->remarks,
                    ]);
                } else {
                    if (empty($lineNumber)) {
                        $maxLine = OutstandingPurchaseOrderItem::where('outstanding_purchase_order_id', $po->id)->max('line_number');
                        $lineNumber = $maxLine ? $maxLine + 1 : 1;
                    }
                    
                    OutstandingPurchaseOrderItem::create([
                        'outstanding_purchase_order_id' => $po->id,
                        'item_variant_id' => $variant ? $variant->id : null,
                        'erp_code' => $erpCode,
                        'item_name_snapshot' => $itemName,
                        'ordered_qty' => (int)$orderedQty,
                        'received_qty' => 0,
                        'unit' => $unit ?: 'PCS',
                        'line_number' => $lineNumber,
                        'remarks' => $remarks ?: null,
                    ]);
                }

                $successCount++;
            }

            // 5. Archival of missing POs in active warehouse context
            $uniqueProcessedPos = array_unique(array_filter($poNumbersProcessed));
            if (!empty($uniqueProcessedPos)) {
                OutstandingPurchaseOrder::forActiveWarehouse()
                    ->whereIn('status', [OutstandingPurchaseOrder::STATUS_PENDING, OutstandingPurchaseOrder::STATUS_PARTIAL])
                    ->whereNotIn('po_number', $uniqueProcessedPos)
                    ->update(['is_archived' => true]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'success' => $successCount,
            'failed' => $failedCount,
            'errors' => $errors
        ];
    }
}
