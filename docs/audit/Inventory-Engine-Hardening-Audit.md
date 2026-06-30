# Operational Hardening Audit Report: Inventory Engine (Phase X)

This document provides a comprehensive operational hardening audit of the Warehouse WMS inventory engine, analyzing code execution paths, single-source-of-truth invariants, database transaction scopes, concurrency safety, performance scalability, and system dependencies.

---

## 1. Inventory Engine Architecture Report

### Execution Flow Diagrams

#### A. Stock In Flow
```
[StockInPage (Livewire)]
        ↓
[InventoryService @ moveStock()]
        ↓  (Starts DB::Transaction)
        ↓  (Pessimistic Row Lock: Bin::lockForUpdate())
        ↓  (Inserts StockMovement with type 'IN')
        ↓  (Updates locked Bin.current_qty += qty)
        ↓  (Saves Bin)
[ItemVariant] (Updates last_stock_in_at via Model Event or separate logic)
```

#### B. Stock Out Flow
```
[ScanPage (Livewire)]
        ↓  (Loops through cart items & resolves available bins)
[InventoryService @ moveStock()]
        ↓  (Starts DB::Transaction)
        ↓  (Pessimistic Row Lock: Bin::lockForUpdate())
        ↓  (Asserts locked Bin.current_qty >= requested qty)
        ↓  (Inserts StockMovement with type 'OUT')
        ↓  (Updates locked Bin.current_qty -= qty)
        ↓  (Saves Bin)
[ItemVariant] (Updates last_stock_out_at)
```

#### C. Inventory Adjustment Flow
```
[OpnamePage (Livewire)]
        ↓  (Creates InventoryAdjustment [WAITING_APPROVAL])
[InventoryAdjustmentsPage (Livewire)]
        ↓  (Manager approves item; locks InventoryAdjustmentItem)
[InventoryService @ moveStock()]
        ↓  (Starts DB::Transaction)
        ↓  (Pessimistic Row Lock: Bin::lockForUpdate())
        ↓  (Inserts StockMovement with type 'ADJUSTMENT' [positive or negative])
        ↓  (Updates locked Bin.current_qty += variance)
        ↓  (Saves Bin)
[ItemVariant] (Updates last_opname_at)
```

---

## 2. Single Source of Truth Audit

The primary custodian of inventory mutations is `App\Services\Inventory\InventoryService`.

### Identified Pattern Discrepancies / Bypasses:
1. **Unassigned-Bin Ingestion in `StockInPage@submit` (Line 465)**:
   - When an item variant has no bin assigned during stock ingestion, the controller bypasses `InventoryService` and creates a `StockMovement` row directly:
     ```php
     \App\Models\StockMovement::create([
         'item_variant_id'       => $item['item_variant_id'],
         'bin_id'                => null,
         'supplier_id'           => $item['supplier_id'],
         'type'                  => 'IN',
         'qty'                   => $item['qty'],
         ...
     ]);
     ```
   - *Audit Assessment*: Although this does not corrupt any `Bin.current_qty` (since `bin_id` is null), it bypasses the low-level service abstraction layer and directly inserts raw ledger rows.
2. **Initial Stock Creations in Bulk Imports & Forms**:
   - `BulkImport.php`, `ItemForm.php`, and `ImportItemsJob.php` instantiate `Bin` records with `current_qty => 0` during database insertion.
   - *Audit Assessment*: This is acceptable because these are brand-new records. Any initial non-zero stock is correctly funneled through `InventoryService@moveStock` immediately afterward.

---

## 3. Ledger Integrity Audit

The critical ledger invariant is:
$$\text{Current Bin Quantity} = \sum (\text{StockMovement.qty}) \quad \text{for the same Bin ID}$$

### Potential Invariant Violation Vectors:
1. **Manual Seeders & Test Mappings**:
   - Standard seeds and unit test setups (e.g. `$bin->update(['current_qty' => 10])`) mutate bin quantities directly without writing corresponding `StockMovement` rows.
2. **Bypassed Error Handling in Transaction Boundaries**:
   - If an exception occurs after a raw `StockMovement` insertion but before the transaction completes in non-service classes, the database rollback safeguards it. However, if the operation is executed outside a database transaction, an application crash between the movement insert and bin update results in ledger drift.
3. **Database Cascade Deletions**:
   - Bins are subject to cascade deletion rules when parent models are deleted. If a `Bin` is deleted, its related `StockMovements` will also be deleted due to the `onDelete('cascade')` foreign key rule, preserving consistency (both sum to zero). However, this wipes out historical ledger history.

---

## 4. Transaction Audit

### Analysis of Transaction Blocks:
* **`InventoryService@moveStock` & `@executeReversal`**: Properly wrapped in `DB::transaction()` with full rollback safety. Exceptions are re-thrown, triggering automatic database rollback.
* **`StockInPage@submit`**: Wrapped in a transaction block that coordinates `moveStock` and receipt session updates.
* **`ScanPage@submit`**: Wrapped in a transaction coordinating receipt header creation, multiple bin loops, and stock out actions.
* **`OpnamePage@saveItem`**: Separates the header creation and item logging into two sequential transactions. If the item creation fails, a `WAITING_APPROVAL` header is orphaned. No stock is mutated, so inventory is safe, but it leaves database garbage.

### Unprotected Mutations:
* Livewire cart draft updates (e.g. adding items to list/cart) are not transaction-protected. Because these represent stateful draft tables (`stock_in_items`), transaction wrapping is not strictly required.

---

## 5. Concurrency Audit

### Risk Profiles:
1. **Double-Approval Concurrency**:
   - Handled cleanly in `InventoryAdjustmentsPage` via `lockForUpdate()` on the `InventoryAdjustmentItem` record inside the transaction. Concurrent requests are blocked; the second request reads the mutated state (`APPROVED`/`REJECTED`) and aborts immediately.
2. **Negative Stock Concurrency**:
   - Handled cleanly in `InventoryService@moveStock` via `lockForUpdate()` on the `Bin` record. Parallel stock deductions are serialized.
3. **Deadlock Risk**:
   - *Risk Identified*: In `ScanPage@submit`, the cart items are processed in the order they were inserted. If Operator A locks Bin X then Bin Y, and Operator B locks Bin Y then Bin X concurrently, a database deadlock occurs.
   - *Mitigation*: Sort the cart items by `bin_id` prior to processing, forcing locks to be acquired in a deterministic order.

---

## 6. Inventory Service Audit

### Separation of Concerns:
- `InventoryService` remains strictly workflow-agnostic. It does not contain references to approvals, user roles, governance tables, or view templates.

### Leakage Points:
- The service reads HTTP/WMS state directly from the session:
  - `session()->get('active_warehouse_id')`
  - `session()->get('wms_terminal_id')`
  - `session()->getId()`
- *Audit Assessment*: This couples the low-level domain service to the web request lifecycle, making it harder to invoke safely from CLI commands or queue workers (where sessions are absent) without risking fallback errors.

---

## 7. Idempotency Audit

### Analysis:
- **Approval Actions**: High protection. Double submissions are rejected by the `$item->status !== 'WAITING'` assertion.
- **Stock In Ingestion**:
  - *Risk Identified*: `StockInPage@submit` does not pessimistically lock the `StockInReceipt` row nor check for duplicate requests. A rapid double-click on the submit button before the first transaction commits can trigger double ingestion of the same items.
- **Stock Out Ingestion**:
  - *Risk Identified*: `ScanPage@submit` lacks a request deduplication token. Double-clicking submit can create duplicate stock transaction headers and double-deduct inventory.

---

## 8. Performance Audit

Under load conditions (100k movements, 50k bins, 20k variants):
1. **Aggregated Stock Subqueries (`ItemService@getPaginatedItems`)**:
   - Grouping the entire `bins` and `stock_movements` tables by `item_variant_id` to aggregate quantities and fetch last movements will result in full index/table scans. As these tables grow, this query will degrade.
   - *Mitigation*: Introduce composite indexes on `bins(item_variant_id, current_qty)` and `stock_movements(item_variant_id, created_at)`.
2. **Global Aggregation Drift**:
   - The aggregated stock queries do not filter by the active warehouse, displaying global stock quantities on the inventory dashboard instead of warehouse-isolated stock.

---

## 9. Artisan Health Check Design

### Proposed Command: `php artisan inventory:audit`

#### Check Invariant Algorithm:
```php
// 1. Verify Bin balance against Stock Movements
$driftingBins = DB::table('bins')
    ->leftJoin('stock_movements', 'bins.id', '=', 'stock_movements.bin_id')
    ->select('bins.id', 'bins.code', 'bins.current_qty', DB::raw('SUM(stock_movements.qty) as ledger_qty'))
    ->groupBy('bins.id', 'bins.code', 'bins.current_qty')
    ->havingRaw('bins.current_qty != COALESCE(SUM(stock_movements.qty), 0)')
    ->get();

// 2. Identify orphaned Stock Movements (pointing to deleted/missing bins)
$orphanedMovements = DB::table('stock_movements')
    ->leftJoin('bins', 'stock_movements.bin_id', '=', 'bins.id')
    ->whereNull('bins.id')
    ->whereNotNull('stock_movements.bin_id')
    ->get();

// 3. Verify negative quantities
$negativeBins = DB::table('bins')->where('current_qty', '<', 0)->get();

// 4. Verify foreign key integrity
$brokenVariants = DB::table('bins')
    ->leftJoin('item_variants', 'bins.item_variant_id', '=', 'item_variants.id')
    ->whereNull('item_variants.id')
    ->get();
```

---

## 10. Dependency Map

* **Livewire Pages**: `StockInPage`, `ScanPage`, `InventoryAdjustmentsPage`, `ItemForm`, `BulkImport`.
* **Queued Jobs**: `ImportItemsJob`.
* **Impact of changes**: Any modification to the signature of `InventoryService@moveStock` requires updating all six components.

---

# FINAL VERDICT

### **REQUIRES ENGINE REFACTOR**

#### Technical Justification:
1. **Unassigned Bin Bypass**: `StockInPage@submit` bypasses the single source of truth (`InventoryService`) to write directly to `StockMovement`.
2. **HTTP Session Leakage**: `InventoryService` relies directly on PHP session globals (`session()->get()`), making it unstable for CLI commands or queue workers.
3. **Double Ingestion Risk**: Inbound and outbound submission queues lack request deduplication tokens, creating a double-click transaction risk.
4. **Deadlock Vulnerability**: `ScanPage@submit` locks multiple bins in an arbitrary order depending on how items were scanned, creating a deadlock risk under concurrent checkout operations.
