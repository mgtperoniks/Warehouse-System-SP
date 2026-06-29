# Engineering Handover: Inventory Adjustment Approval Engine (Phase 1)
**Date**: 2026-06-29  
**Status**: Architectural Foundation Completed  
**Role**: Engineering Handover Document (ADR Format)

---

## 1. Executive Summary

This document serves as the architectural handover for the **Inventory Adjustment Approval Engine (Phase 1)** in the Warehouse Sparepart system. 

The core objectives of this system have evolved from a simple direct stock-update model to a comprehensive, audit-ready governance framework. Previously, the stock opname module immediately wrote changes to the database upon submission. To meet strict industrial governance requirements, we have separated the operational scanning workflow from the ledger commit. 

The system now enforces an **approval gate**: when an operator detects a physical discrepancy, the variance is logged with a mandatory reason code and held in a `WAITING_APPROVAL` status queue. The inventory ledger, bin quantities, and stock movements are only modified after a Manager PPIC reviews and explicitly approves the item.

---

## 2. Completed Work (Current State)

### Warehouse Core
- **Warehouse Switching**: Handled dynamically via `WarehouseSwitchController@switchWarehouse`.
- **Contamination Control**: Switching warehouses automatically wipes the operator's active scanner carts (`scan_cart` session) and marks active stock-in receipt drafts as `ABANDONED` to prevent data cross-contamination.
- **Warehouse Context Session**: Active warehouse ID, code, and name are stored under `active_warehouse_id`, `active_warehouse_code`, and `active_warehouse_name` session variables.
- **Warehouse Context Middleware**: `WmsWarehouseContextMiddleware` ensures that if a user is authenticated but lacks a warehouse context in their session, it defaults to the first warehouse they are authorized to access.
- **Local Query Scopes**: Models (`Bin`, `StockTransaction`, `StockMovement`, `StockInReceipt`) implement the `scopeForActiveWarehouse($query)` method to restrict queries to the current active warehouse.

### Stock Out ERP Transfer (Gold Standard)
The Stock Out ERP Transfer module has been fully refactored and certified production-ready.
- **Item-Level ERP Transfer**: Tracks synchronization status of individual child items rather than bulk transactions.
- **Parent Aggregate Synchronization**: Parent status is dynamically updated and derived from the child items' sync statuses.
- **Stable KPIs**: Global KPIs (e.g. Total Queue, Pending Sync, Failed Sync) remain stable and constant when switching between datatable filtering tabs.
- **Department Grouping**: Items are aggregated by department codes for clean overview displays.
- **Remaining Counter & Global Indicators**: Live progress bars track sync completion.
- **Filtering Views**: Tab views for `NOT_STARTED` and `COMPLETED` records provide clean work queues.
- **Performance Optimizations**: Rewritten queries eliminate N+1 query bottlenecks during bulk records synchronization.
- **Production Verification**: Loloș audit production with zero regressions.

### Stock In ERP Transfer
The Stock In ERP Transfer module was refactored to mirror the Stock Out architecture.
- **Architectural Symmetry**: Implements the identical item-level sync engine.
- **KPI Stability**: KPI calculations are computed globally, maintaining constant totals regardless of the active filter tab.
- **Parent Sync**: Updates `StockInReceipt` aggregates atomically as child rows get pushed to the ERP bridge.
- **Workflow & UI Symmetry**: Shares the same visual cues, status indicators, and performance optimizations as the Outbound workflow.

---

## 3. Architectural Decisions

### KPI Philosophy
Global KPIs shown on dashboard queues (e.g., total pending items, failed transfers) **must never change** when a user clicks different filter tabs (e.g., viewing Completed vs. Pending items). 
- *Rationale:* If KPIs fluctuate based on the active tab, the user loses their absolute reference point for the total pending work. The filter tab should only change the visible datatable rows; the KPIs must remain the global source of truth for the entire queue.

### Item-Level Tracking
Every individual item in a transaction or receipt is tracked with its own status lifecycle.
- *Rationale:* Legacy systems (such as the previous VB6 ERP) grouped transactions as monolithic documents. If a single item in a 50-line transfer failed to sync or had incorrect quantities, the entire document was locked and rejected. Item-level tracking allows successful items to process immediately while isolating failed rows for correction.

### Parent Aggregate
The parent document status (e.g., `StockInReceipt->status` or `InventoryAdjustment->status`) must always be **derived** from the states of its child items. It must never be edited manually.
- *Rationale:* Derived status ensures structural consistency. If a parent is marked `COMPLETED` but still contains a child item marked `WAITING`, the database is in an invalid state. Status checks must run bottom-up.

### Governance Philosophy
Operational workflows must remain decoupled from governance workflows.
- *Rationale:* Operators on the warehouse floor need to scan items and log variances as fast as possible. Forcing them to wait for managerial approvals or navigate multi-page forms halts floor operations. Decoupling allows operators to log the audit event and immediately move to the next bin, while the manager acts as an asynchronous approval gate in a separate control view.

---

## 4. Inventory Adjustment Vision

The complete lifecycle for inventory adjustments is defined below:

```
[Physical Audit]
       ↓ (Variance detected: System Qty != Physical Qty)
[Mandatory Reason & Notes entered]
       ↓ (Commit clicked by Operator)
[WAITING_APPROVAL status set; adjustment draft created]
       ↓ (Database stock, Bins, and Ledger remain UNCHANGED)
[Manager PPIC reviews items individually in queue]
       ↓ (Manager clicks Approve)
[DB::transaction wraps operation]
       ↓
[InventoryService@moveStock processes variance]
       ↓
[Bin table current_qty updated]
       ↓
[StockMovement ledger record written]
       ↓
[Adjustment Item status set to APPROVED; Header synced to COMPLETED]
```

> [!IMPORTANT]
> **Crucial Rule:** `InventoryService` is the **only** component in the application allowed to mutate stock quantities. To prevent business logic duplication and race conditions, the approval engine must invoke `InventoryService@moveStock` to adjust the database. It must never update the `Bin` table directly.

---

## 5. Inventory Adjustment Architecture

### Header (`inventory_adjustments`)
- Represents a daily adjustment session.
- **Cardinality**: `1` Header to `N` Items.
- **Grouping**: To prevent document bloat, adjustments are automatically grouped by **Active Warehouse**, **Operator ID**, and **Calendar Date**. If an operator submits another variance on the same day, it is appended to the existing `WAITING_APPROVAL` header. A new header is only created if no waiting header exists for that day/operator combo.
- **Header Statuses**:
  - `WAITING_APPROVAL`: Contains one or more items still marked `WAITING`.
  - `COMPLETED`: All child items have been processed (status `APPROVED` or `REJECTED`).

### Items (`inventory_adjustment_items`)
- Represents a specific variance audit row.
- **Status Lifecycle**: `WAITING` -> `APPROVED` or `REJECTED`.
- **Audit Snapshots**: Every item row must store immutable text snapshots of the target master data at the time of submission:
  - `item_name_snapshot`
  - `erp_code_snapshot`
  - `bin_code_snapshot`
  - `unit_snapshot`
  - *Rationale:* Ensures that historical audit sheets and printed reports are immutable. If an item name changes in the master catalog next month, the audit record reflects the name at the moment the variance occurred.

### Header Status Synchronization Helper
Whenever an item's status is modified (Approve or Reject), the parent header's status must be synchronized:
```php
public static function synchronizeStatus(int $headerId): void
{
    $header = self::findOrFail($headerId);
    $hasWaiting = $header->items()->where('status', 'WAITING')->exists();
    
    $targetStatus = $hasWaiting ? 'WAITING_APPROVAL' : 'COMPLETED';
    
    if ($header->status !== $targetStatus) {
        $header->update(['status' => $targetStatus]);
    }
}
```

---

## 6. Role Visibility Rules

### Admin / Operator
- **Permissions**:
  - Can view their **own** adjustment entries only.
  - Can trigger adjustments by auditing bins on the `/opname` page.
  - Can resubmit rejected items (by returning to the `/opname` page and scanning the bin again, generating a new adjustment).
  - Cannot view adjustments created by other admins.
  - Cannot approve or reject adjustments.

### Manager PPIC
- **Permissions**:
  - Can view **all** adjustments within the currently active warehouse context.
  - Can approve adjustment items.
  - Can reject adjustment items (must supply a `reject_reason` in a mandatory modal).
  - Can print BASO documents.
  - Cannot manually edit adjustment quantities in the queue (to prevent bypass of audit trails).

---

## 7. Warehouse Isolation Rules

Data isolation must be strictly enforced at the query level to prevent warehouse leaks:
- **Admin Queries**: Must always filter by the active warehouse AND the creator's ID.
  ```php
  InventoryAdjustment::forActiveWarehouse()->where('operator_id', auth()->id())
  ```
- **Manager Queries**: Must filter by the active warehouse only.
  ```php
  InventoryAdjustment::forActiveWarehouse()
  ```
- **Warehouse Selection**: Utilizes the existing top-bar navigation switcher. No new warehouse selector or dropdown is permitted on the `/inventory-adjustments` page.

---

## 8. BASO Philosophy

The **Berita Acara Stock Opname (BASO)** is an official document generated from a daily adjustment session.
- It is **not** printed per individual item; it compiles the entire daily group (Header `1` -> `N` Items) into a single report.
- **Expected PDF Layout**:
  - **Header**: Document number (`adjustment_no`), Warehouse Name, Operator Name, and Date.
  - **Table**: List of all audited items, system quantities, physical counts, variances, and reason codes.
  - **Footer**: Double-signature blocks. Every page must contain signature fields for the **Operator (Auditor)** and the **Manager PPIC (Approver)**.

---

## 9. Future Scalability Decisions

- **No Shift UI**: Shifting logic is omitted. In the future, shift assignments will be resolved automatically based on the logged-in user's schedule metadata to avoid slowing down floor workflows.
- **Metadata Snapshots**: Keeping snapshots text-based prevents migration bottlenecks if variant/SKU schemas are refactored in future updates.

---

## 10. UI/UX Principles

- **Fast Operator Flow**: The operator screen (`/opname`) must prioritize fast, hands-free scanning. Minimizing dialog popups keeps throughput high.
- **Context-Aware Fields**: The Reason dropdown and Notes textarea should remain hidden on `/opname` until a difference between System Qty and Physical Qty is entered.
- **Single Screen Review**: The `/inventory-adjustments` dashboard handles all actions (viewing, approving, and rejecting with modals) on a single screen without nested redirect loops.

---

## 11. Things Explicitly Rejected

- **Multiple Draft Pages**: Rejected. Operators should not manage multiple "draft" adjustments; adjustments are logged as immutable waiting queues immediately.
- **One Document per Variance**: Rejected. Creating a separate document for every single item variance is administrative spam.
- **Manager Editing Quantities**: Rejected. If a manager disagrees with a count, they must reject the row and instruct the operator to scan it again. Allowing managers to edit numbers bypasses physical verification.
- **Dynamic KPI Changes on Filter Click**: Rejected. Tab switching must not alter global count summaries.
- **Manual Shift Selection**: Rejected to preserve floor efficiency.

---

## 12. Production Readiness

### Production-Ready Abstractions
- Outbound Transaction Ledger (`StockTransaction`)
- Inbound Receipt Pipeline (`StockInReceipt`)
- ERP Transfer Bridge (`StockOutItem` and `StockInItem` Sync queues)

### Under Development
- Inventory Adjustment Approval Engine (Phase 1 Database, Models, and Livewire interfaces)

---

## 13. Next Development Session

The next engineer should implement Phase 1 by executing the following checklists in order:

### Phase 2: Database Schema & Master Seeders
1. Create migration `create_adjustment_reason_master_table`.
2. Create migration `create_inventory_adjustments_table`.
3. Create migration `create_inventory_adjustment_items_table`.
4. Create `AdjustmentReasonMaster` model and seeder (populated with default codes: `FOUND_MISSING_ITEM`, `COUNTING_ERROR`, `WRONG_BIN`, `DAMAGED_ITEM`, `MOVED_WITHOUT_SCAN`, `RETURN_FOUND`, `SYSTEM_ERROR`, `OTHER`).

### Phase 3: OpnamePage Modification
1. Bind input properties for `reasonCode` and `notes` in `OpnamePage.php`.
2. Update Blade template to show Reason dropdown and Notes textarea only when `difference !== 0`.
3. If Reason is `'OTHER'`, notes must be mandatory. Disable the submit button until validation passes.
4. Modify `saveItem()`:
   - If `difference === 0`, execute the original immediate log flow.
   - If `difference !== 0`, locate or create the daily `InventoryAdjustment` header (status: `WAITING_APPROVAL`). Create the `InventoryAdjustmentItem` detail row (status: `WAITING`) with snapshots. Do **not** update the bin or write to stock movements.
   - Fix the existing search scoping leak: chain `forActiveWarehouse()` during bin resolution.

### Phase 4: Adjustment Queue Dashboard (`/inventory-adjustments`)
1. Create `App\Livewire\Governance\InventoryAdjustmentsPage` and register it in `routes/web.php`.
2. Build two-role filter queries:
   - Operator: sees own adjustments inside active warehouse.
   - Manager: sees all adjustments inside active warehouse.
3. Build the Manager actions:
   - `approve(itemId)`: Start `DB::transaction()`, call `InventoryService@moveStock`, update item to `APPROVED` (marking `approved_by` and `approved_at`), and run header status synchronization.
   - `reject(itemId, reason)`: Open the reject modal, validate `reject_reason`, update item status to `REJECTED` (marking `rejected_by`, `rejected_at`, and `reject_reason`), and run header status synchronization.

### Phase 5: PDF BASO Generation (Future Phase)
1. Set up PDF export routes.
2. Render daily grouped adjustment data with dual signature sign-offs on each page.

---

## 14. Important Engineering Rules

1. **Do Not Break Transactions**: Do not alter `StockInPage` or `ScanPage`. They must remain functional and decoupled from the new adjustment queues.
2. **Single Source of Truth**: The `InventoryService` remains the **only** component allowed to execute stock mutations. No new stock-update database queries may be introduced.
3. **Strict Scoping**: Every query loading adjustment lists, items, or bins must be chained with `forActiveWarehouse()`.
4. **Clean Audit Trail**: Reversals or adjustments must write unique, immutable rows to the ledger. Deleting or modifying existing entries in `stock_movements` is strictly forbidden.
