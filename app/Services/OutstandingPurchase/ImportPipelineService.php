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

        $newLinesCount = 0;
        $updatedLinesCount = 0;
        $unchangedLinesCount = 0;
        $wmsCompletedCount = 0;
        $wmsPartialCount = 0;
        $wmsPendingCount = 0;
        $erpBehindCount = 0;
        $erpBehindLines = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                // Normalize input keys
                $poNumber = trim($row['po_number'] ?? '');
                $supplierName = trim($row['supplier_name'] ?? '');
                $supplierCode = trim($row['supplier_code'] ?? '');
                $departmentName = trim($row['department_name'] ?? $row['department'] ?? $row['dept'] ?? $row['dept_pemesan'] ?? '');
                $poDateRaw = $row['po_date'] ?? null;
                $expectedDateRaw = $row['expected_date'] ?? null;
                $erpCode = trim($row['erp_code'] ?? '');
                $itemName = trim($row['item_name'] ?? '');
                $orderedQty = isset($row['ordered_qty']) ? (float)$row['ordered_qty'] : 0.0;
                $erpReceivedQty = isset($row['erp_received_qty']) ? (float)$row['erp_received_qty'] : (isset($row['received_qty']) ? (float)$row['received_qty'] : 0.0);
                $erpOutstandingQty = isset($row['erp_outstanding_qty']) ? (float)$row['erp_outstanding_qty'] : max(0.0, $orderedQty - $erpReceivedQty);
                $unit = trim($row['unit'] ?? 'PCS');
                $lineNumber = isset($row['line_number']) && $row['line_number'] !== '' ? (int)$row['line_number'] : null;
                $remarks = trim($row['remarks'] ?? '');

                // Validation
                if (empty($poNumber) || empty($erpCode)) {
                    // Skip empty rows silently
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
                    ->orWhere('phone', $supplierName)
                    ->first();

                // 2. Find or Create Parent PO (Search across ALL POs in this warehouse regardless of status)
                $po = OutstandingPurchaseOrder::where('warehouse_id', $warehouseId)
                    ->where('po_number', $poNumber)
                    ->first();

                if ($po) {
                    $poUpdateData = [
                        'supplier_id' => $supplier ? $supplier->id : $po->supplier_id,
                        'supplier_name_snapshot' => $supplierName,
                        'supplier_code_snapshot' => !empty($supplierCode) ? $supplierCode : $po->supplier_code_snapshot,
                        'expected_date' => $expectedDate ?: $po->expected_date,
                        'imported_at' => now(),
                        'is_archived' => false, // Ensure active when imported
                    ];
                    if (!empty($departmentName)) {
                        $poUpdateData['department_name'] = $departmentName;
                    }
                    $po->update($poUpdateData);
                } else {
                    $po = OutstandingPurchaseOrder::create([
                        'warehouse_id' => $warehouseId,
                        'supplier_id' => $supplier ? $supplier->id : null,
                        'supplier_name_snapshot' => $supplierName,
                        'supplier_code_snapshot' => $supplierCode ?: null,
                        'department_name' => $departmentName ?: null,
                        'po_number' => $poNumber,
                        'po_date' => $poDate,
                        'expected_date' => $expectedDate,
                        'status' => OutstandingPurchaseOrder::STATUS_PENDING,
                        'erp_sync_status' => 'IN_SYNC',
                        'is_archived' => false,
                        'source' => $source,
                        'remarks' => $remarks ?: null,
                        'imported_at' => now(),
                    ]);
                }

                $poNumbersProcessed[] = $poNumber;

                // 3. Resolve Item Variant ID
                $variant = ItemVariant::resolveVariant($erpCode, $itemName);

                // 4. Find or Create PO Line Item (Search across ALL statuses)
                $poItem = OutstandingPurchaseOrderItem::where('outstanding_purchase_order_id', $po->id)
                    ->where('erp_code', $erpCode)
                    ->first();

                if (!$poItem) {
                    // NEW PO LINE INSERT
                    if (empty($lineNumber)) {
                        $maxLine = OutstandingPurchaseOrderItem::where('outstanding_purchase_order_id', $po->id)->max('line_number');
                        $lineNumber = $maxLine ? $maxLine + 1 : 1;
                    }

                    OutstandingPurchaseOrderItem::create([
                        'outstanding_purchase_order_id' => $po->id,
                        'item_variant_id' => $variant ? $variant->id : null,
                        'erp_code' => $erpCode,
                        'item_name_snapshot' => $itemName,
                        'department_name' => $departmentName ?: $po->department_name,
                        'ordered_qty' => $orderedQty,
                        'received_qty' => 0.0,
                        'erp_ordered_qty' => $orderedQty,
                        'erp_received_qty' => $erpReceivedQty,
                        'erp_outstanding_qty' => $erpOutstandingQty,
                        'erp_sync_status' => 'IN_SYNC',
                        'erp_snapshot_at' => now(),
                        'unit' => $unit ?: 'PCS',
                        'line_number' => $lineNumber,
                        'remarks' => $remarks ?: null,
                    ]);

                    $newLinesCount++;
                    $wmsPendingCount++;
                } else {
                    // EXISTING PO LINE - RECONCILIATION & IMMUTABILITY PROTECTION
                    $wmsReceived = (float)$poItem->received_qty;
                    $wmsOrdered = (float)$poItem->ordered_qty;
                    $wmsPending = (float)$poItem->pending_qty;
                    $isCompleted = ($poItem->status === 'Closed' || ($wmsReceived > 0 && $wmsPending <= 0.0001));
                    $isPartial = ($poItem->status === 'Partial' || ($wmsReceived > 0 && $wmsPending > 0.0001));

                    // Check if ERP is lagging behind WMS receiving events
                    if ($wmsReceived > $erpReceivedQty) {
                        $erpSyncStatus = 'ERP_BEHIND';
                        $erpBehindCount++;
                        $erpBehindLines[] = [
                            'po_number' => $po->po_number,
                            'erp_code' => $poItem->erp_code,
                            'item_name' => $poItem->item_name_snapshot,
                            'unit' => $poItem->unit,
                            'wms_status' => $poItem->status,
                            'wms_received' => $wmsReceived,
                            'wms_pending' => $wmsPending,
                            'erp_ordered' => $orderedQty,
                            'erp_received' => $erpReceivedQty,
                            'erp_outstanding' => $erpOutstandingQty,
                        ];
                    } else {
                        $erpSyncStatus = 'IN_SYNC';
                    }

                    // Prepare update payload for ERP snapshot & metadata
                    $updatePayload = [
                        'erp_ordered_qty' => $orderedQty,
                        'erp_received_qty' => $erpReceivedQty,
                        'erp_outstanding_qty' => $erpOutstandingQty,
                        'erp_sync_status' => $erpSyncStatus,
                        'erp_snapshot_at' => now(),
                        'remarks' => $remarks ?: $poItem->remarks,
                    ];

                    if (!empty($departmentName)) {
                        $updatePayload['department_name'] = $departmentName;
                    }

                    if (!$poItem->item_variant_id && $variant) {
                        $updatePayload['item_variant_id'] = $variant->id;
                    }

                    // PROTECT WMS RECEIVING STATE
                    if ($isCompleted) {
                        // WMS is already COMPLETED: Never decrease ordered_qty below received_qty, Never change received_qty!
                        $wmsCompletedCount++;
                    } elseif ($isPartial) {
                        // WMS is PARTIAL: Never change received_qty! If ERP ordered qty increased, we can adjust ordered_qty
                        if ($orderedQty > $wmsReceived) {
                            $updatePayload['ordered_qty'] = $orderedQty;
                        }
                        $wmsPartialCount++;
                    } else {
                        // WMS is PENDING: Safe to update ordered_qty
                        $updatePayload['ordered_qty'] = $orderedQty;
                        $wmsPendingCount++;
                    }

                    $poItem->fill($updatePayload);

                    if ($poItem->isDirty()) {
                        $poItem->save();
                        $updatedLinesCount++;
                    } else {
                        $unchangedLinesCount++;
                    }
                }

                $successCount++;
            }

            // 5. Archival of missing POs in active warehouse context (Only full ERP_IMPORT runs)
            $uniqueProcessedPos = array_unique(array_filter($poNumbersProcessed));
            if ($source === 'ERP_IMPORT' && !empty($uniqueProcessedPos)) {
                OutstandingPurchaseOrder::forActiveWarehouse()
                    ->whereIn('status', [OutstandingPurchaseOrder::STATUS_PENDING, OutstandingPurchaseOrder::STATUS_PARTIAL])
                    ->whereNotIn('po_number', $uniqueProcessedPos)
                    ->update(['is_archived' => true]);
            }

            // 6. Recalculate status & ERP sync status on all processed POs
            foreach ($uniqueProcessedPos as $pNum) {
                $pObj = OutstandingPurchaseOrder::where('warehouse_id', $warehouseId)
                    ->where('po_number', $pNum)
                    ->first();
                if ($pObj) {
                    $pObj->recalculateStatus();
                    $pObj->save();
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'success' => $successCount,
            'failed' => $failedCount,
            'errors' => $errors,
            'summary' => [
                'new_lines' => $newLinesCount,
                'updated_lines' => $updatedLinesCount,
                'unchanged_lines' => $unchangedLinesCount,
                'wms_completed' => $wmsCompletedCount,
                'wms_partial' => $wmsPartialCount,
                'wms_pending' => $wmsPendingCount,
                'erp_behind' => $erpBehindCount,
                'erp_behind_lines' => $erpBehindLines,
            ],
        ];
    }
}
