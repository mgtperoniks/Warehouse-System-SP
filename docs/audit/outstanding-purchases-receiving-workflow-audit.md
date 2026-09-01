# FORENSIC WORKFLOW AUDIT: OUTSTANDING PURCHASES & DIGITAL INCOMING GOODS CHECKING

**Repository:** `C:\laragon\www\Warehouse-System-Sparepart`  
**Date of Audit:** September 1, 2026  
**Auditor:** Antigravity Forensic Engine  
**Audit Scope:** `/outstanding-purchases`, PO Import, Receiving Sessions, Physical Checking on Mobile/Android, Digital Signatures, and A4 Document Generation (`BUKTI PENGECEKAN BARANG DATANG`).  
**Audit Status:** Complete Forensic Investigation (No code or database modifications made).

---

## 1. Executive Summary

This forensic audit evaluates the current implementation of the Outstanding Purchases and Inbound Receiving module against the business requirement of capturing physical incoming goods checking on Android devices and generating the official A4 **"BUKTI PENGECEKAN BARANG DATANG"** form without requiring Admin Sparepart to reconstruct the inspection afterward.

### Key Audit Findings:
1. **Separation of Concerns (PO vs Actual Receiving):** The codebase **already possesses a dedicated 2-tier receiving model** (`receiving_sessions` and `receiving_session_items`) separate from the original PO tables (`outstanding_purchase_orders` and `outstanding_purchase_order_items`). This is an architectural strength: original PO data is not directly overwritten during receiving.
2. **Physical Inspection Data Fidelity (Critical Gaps):**
   - **No `qty_datang` vs `qty_terima` distinction:** The system models `expected_qty` (snapshotted from PO) and `received_qty`. If a supplier delivers 10 pcs on the delivery note (surat jalan) but 2 are rejected due to damage (received = 8), there is no dedicated column to capture the delivered quantity (10) separately from accepted quantity (8). In the current PDF view, `expected_qty` (PO quantity) is incorrectly printed as `QTY DATANG`.
   - **No `hasil_pengecekan` condition field:** There is no field to record qualitative physical inspection results (e.g. `OK`, `RUSAK`, `REJECT`, `DIMENSI BEDA`). The current PDF calculates a mathematical variance formula (`received_qty - expected_qty`), producing `+1`, `-1`, or blank, rather than actual inspection feedback.
   - **Decimal Quantities Truncated:** Database columns (`ordered_qty`, `received_qty`, `expected_qty`) and model casts are strictly `INTEGER`. Real-world fractional goods (e.g., `0.8 KG Nickel` or `12.5 MTR Cable`) cannot be stored without data loss/rounding errors.
   - **Missing `DEPT. PEMESAN`:** Neither the PO import nor the receiving session stores ordering department data. In the current PDF template, `DEPT. PEMESAN` is hardcoded as `&nbsp;` (blank).
3. **Workflow Blockers for Android/Mobile:**
   - **Mandatory 3 Digital Signatures:** `ReceivingSessionPage::finalizeReceiving()` strictly enforces 3 digital signatures (`DISERAHKAN_OLEH`, `DITERIMA_OLEH`, `BAG_GUDANG`) before finalization can proceed. This violates the business requirement where digital signatures must be **optional and non-blocking** (allowing physical signature on printed A4).
   - **Flawed Multi-Session / Partial PO Snapshotting:** When starting a second receiving session for a partial PO, `OutstandingPurchaseShowPage::startReceivingSession()` snapshots `poItem->ordered_qty` instead of `poItem->pending_qty` into `expected_qty`, and pulls in already-completed items, forcing operators to mark them as `REMOVED`.
4. **Final Verdict:** **PARTIALLY READY** (The foundational session architecture, UI layout, touch counter, and DomPDF rendering engine exist, but schema omissions, integer truncation, strict signature blocking, and snapshot bugs prevent the end-to-end business workflow from functioning accurately).

---

## 2. Current Architecture

The existing receiving architecture spans across 4 primary layers:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                             PRESENTATION LAYER                              │
├─────────────────────────────────────────────────────────────────────────────┤
│  1. OutstandingPurchaseIndexPage (Livewire)   -> /outstanding-purchases     │
│  2. OutstandingPurchaseImportPage (Livewire)  -> /outstanding-purchases/import│
│  3. OutstandingPurchaseShowPage (Livewire)    -> /outstanding-purchases/{id}│
│  4. ReceivingSessionPage (Livewire)           -> /receiving/{id}            │
│  5. ReceivingPdfController (Controller)       -> /receiving/{id}/pdf        │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│                              SERVICE LAYER                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│  1. ImportPipelineService: Ingests Excel/Clipboard POs & auto-archives      │
│  2. OutstandingPurchaseOrderImport: Maatwebsite Excel mapping pipeline     │
│  3. InventoryService: Handles WMS Bin-level stock movements (IN/OUT)       │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│                              DATA DOMAIN LAYER                              │
├─────────────────────────────────────────────────────────────────────────────┤
│  Tier 1: EXPECTED STATE (Purchase Orders)                                   │
│    - OutstandingPurchaseOrder (`outstanding_purchase_orders`)              │
│    - OutstandingPurchaseOrderItem (`outstanding_purchase_order_items`)      │
│                                                                             │
│  Tier 2: ACTUAL PHYSICAL RECEIVING EVENT                                    │
│    - ReceivingSession (`receiving_sessions`)                               │
│    - ReceivingSessionItem (`receiving_session_items`)                       │
│    - ReceivingSignature (`receiving_signatures`)                           │
│                                                                             │
│  Tier 3: WMS INVENTORY LEDGER                                               │
│    - StockMovement (`stock_movements`)                                      │
│    - Bin (`bins`)                                                           │
│    - ItemVariant (`item_variants`)                                          │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Current `/outstanding-purchases` Workflow

### Step-by-Step Trajectory:
1. **Navigation:** User accesses `/outstanding-purchases` (`OutstandingPurchaseIndexPage`).
2. **Warehouse Filtering:** Results are scoped to `session('active_warehouse_id')` via `scopeForActiveWarehouse()`.
3. **Healing on Render:** Any PO item without `item_variant_id` triggers `OutstandingPurchaseOrder::healVariantMappings()` attempting to resolve variants against master catalog by `erp_code` or `item_name`.
4. **PO Detail Inspection:** User clicks **"View Details"** leading to `/outstanding-purchases/{id}` (`OutstandingPurchaseShowPage`).
5. **Readiness Gate:** If any item lacks an `item_variant_id`, the button **"READY FOR RECEIVING"** is locked/disabled with label **"COMPLETE ITEM CATALOG FIRST"**.
6. **Session Creation:** When unlocked, clicking **"READY FOR RECEIVING"** executes `OutstandingPurchaseShowPage::startReceivingSession()`, which:
   - Acquires a database lock (`lockForUpdate()`) on the PO.
   - Checks if an active session (`DRAFT` or `READY_REVIEW`) already exists; if so, resumes it.
   - If not, creates a `ReceivingSession` (`status = 'DRAFT'`).
   - Clones all `OutstandingPurchaseOrderItem` rows into `ReceivingSessionItem` rows with `expected_qty = ordered_qty`, `received_qty = 0`, `verification_status = 'PENDING'`.
   - Redirects the browser to `/receiving/{id}` (`ReceivingSessionPage`).
7. **Session Execution:** On `/receiving/{id}`, the checker adjusts `received_qty` using `+` / `-` buttons or manual text input, then clicks **VERIFY** for each line. Lines not received must be removed via **REMOVE** modal with a predefined reason.
8. **Session Progression:** Checker clicks **"COMPLETE VERIFICATION"** -> status changes to `READY_REVIEW`.
9. **Review Stage:** Supervisor/Checker clicks **"REVIEW & CONFIRM"** -> status changes to `REVIEWED`.
10. **Digital Signatures:** Signature modals capture Base64 PNGs via HTML5 Canvas (`signature_pad@4.1.7`) for `DISERAHKAN_OLEH`, `DITERIMA_OLEH`, and `BAG_GUDANG`.
11. **Final WMS Commit:** User clicks **"FINALIZE RECEIVING"** (`finalizeReceiving()`), which:
    - Verifies all 3 signatures are present (throws exception if `< 3`).
    - Validates bins exist in the active warehouse for all verified items.
    - Calls `InventoryService::moveStock()` to increment bin quantities and record `StockMovement` (type `IN`).
    - Increments `OutstandingPurchaseOrderItem::received_qty += $item->received_qty`.
    - Triggers PO status recalculation (`PENDING` -> `PARTIAL` -> `CLOSED`).
    - Marks session as `COMPLETED`.
    - Generates DomPDF document and stores to `storage/app/public/receiving/receiving_session_{id}.pdf`.

---

## 4. Full Data Flow Diagram & Trace

```
PO IMPORT (Excel/Clipboard)
│
├── File: app/Imports/OutstandingPurchaseOrderImport.php
├── Service: app/Services/OutstandingPurchase/ImportPipelineService.php::process()
├── Tables Written:
│     - `outstanding_purchase_orders` (po_number, supplier_name_snapshot, po_date, status=1)
│     - `outstanding_purchase_order_items` (erp_code, item_name_snapshot, ordered_qty, received_qty=0)
└── Note: Missing POs in same warehouse are automatically flagged `is_archived = true`.
      │
      ▼
OUTSTANDING PURCHASE ORDER (Expected State)
│
├── Model: app/Models/OutstandingPurchaseOrder.php
├── Model: app/Models/OutstandingPurchaseOrderItem.php
├── Status Calculation: computed dynamically based on items' pending_qty (`ordered_qty - received_qty`)
      │
      ▼
USER SELECTS PO & INITIATES RECEIVING
│
├── Component: app/Livewire/OutstandingPurchase/OutstandingPurchaseShowPage.php::startReceivingSession()
├── Tables Written:
│     - `receiving_sessions` (warehouse_id, outstanding_purchase_order_id, status='DRAFT', created_by)
│     - `receiving_session_items` (receiving_session_id, outstanding_purchase_order_item_id, expected_qty, received_qty=0, verification_status='PENDING')
      │
      ▼
RECEIVING & CHECKING EVENT (Mobile / Android Screen)
│
├── Component: app/Livewire/Receiving/ReceivingSessionPage.php
├── Methods: incrementQty(), decrementQty(), setQtyManual(), verifyLine(), removeLine(), saveDraft()
├── Table Mutated: `receiving_session_items` (received_qty, verification_status, removed_reason, remarks)
      │
      ▼
VERIFICATION & REVIEW
│
├── Component: app/Livewire/Receiving/ReceivingSessionPage.php::completeVerification() (status -> 'READY_REVIEW')
├── Component: app/Livewire/Receiving/ReceivingSessionPage.php::reviewAndConfirm() (status -> 'REVIEWED')
      │
      ▼
DIGITAL SIGNATURES (Canvas Capture)
│
├── Component: app/Livewire/Receiving/ReceivingSessionPage.php::saveSignature($role, $signatureData)
├── Storage: storage/app/public/signatures/session_{id}_{role}_{timestamp}.png
├── Table Written: `receiving_signatures` (receiving_session_id, role, signature_path, signed_by, signed_at)
      │
      ▼
FINAL TRANSACTION COMMIT & STOCK ENTRY
│
├── Component: app/Livewire/Receiving/ReceivingSessionPage.php::finalizeReceiving()
├── Service: app/Services/Inventory/InventoryService.php::moveStock()
├── Tables Mutated:
│     - `bins` (current_qty += received_qty)
│     - `stock_movements` (item_variant_id, bin_id, supplier_id, type='IN', qty, reference)
│     - `outstanding_purchase_order_items` (received_qty += received_qty)
│     - `outstanding_purchase_orders` (status recalculation: PENDING/PARTIAL/CLOSED)
│     - `receiving_sessions` (status='COMPLETED', completed_at=now(), pdf_path)
      │
      ▼
A4 PDF GENERATION & PRINT
│
├── Method: ReceivingSessionPage::generatePdfDocument()
├── Controller: app/Http/Controllers/Receiving/ReceivingPdfController.php::view()
├── View: resources/views/reports/receiving-inspection-pdf.blade.php
└── Output: A4 Portrait PDF ("BUKTI PENGECEKAN BARANG DATANG")
```

---

## 5. Existing Database Model

### Table 1: `outstanding_purchase_orders`
| Column | Type | Nullable | Description / Business Purpose |
|---|---|---|---|
| `id` | `bigint unsigned` | NO | Primary Key |
| `warehouse_id` | `bigint unsigned` | NO | FK to `warehouses.id` (Domain Isolation) |
| `receiving_session_id`| `bigint unsigned` | YES | FK pointer to latest session (Convenience) |
| `supplier_id` | `bigint unsigned` | YES | FK to `suppliers.id` |
| `supplier_name_snapshot`| `varchar(255)` | NO | Historical snapshot of supplier name |
| `supplier_code_snapshot`| `varchar(255)` | YES | Supplier code from ERP |
| `po_number` | `varchar(255)` | NO | PO document identifier |
| `document_reference` | `varchar(255)` | YES | ERP reference document |
| `po_date` | `date` | NO | Date PO was issued |
| `expected_date` | `date` | YES | Expected delivery arrival date |
| `status` | `int` | NO | 1 = Pending, 2 = Partial, 3 = Closed |
| `is_archived` | `tinyint(1)` | NO | Hidden/Archived flag |
| `source` | `varchar(255)` | NO | `ERP_IMPORT` or `CLIPBOARD` |
| `remarks` | `text` | YES | General PO remarks |
| `imported_at` | `timestamp` | YES | Ingestion timestamp |
| `created_at` / `updated_at`| `timestamp` | YES | Timestamps |

### Table 2: `outstanding_purchase_order_items`
| Column | Type | Nullable | Description / Business Purpose |
|---|---|---|---|
| `id` | `bigint unsigned` | NO | Primary Key |
| `outstanding_purchase_order_id`| `bigint unsigned`| NO | FK to `outstanding_purchase_orders.id` |
| `item_variant_id` | `bigint unsigned` | YES | FK to `item_variants.id` (Master Catalog) |
| `erp_code` | `varchar(255)` | NO | ERP Part / Item code |
| `item_name_snapshot` | `varchar(255)` | NO | Item description at PO time |
| `ordered_qty` | `int` | NO | **Integer** ordered quantity (PO expected) |
| `received_qty` | `int` | NO | **Integer** cumulative accepted quantity |
| `unit` | `varchar(255)` | NO | Unit of measure (PCS, KG, MTR, etc.) |
| `line_number` | `int` | YES | ERP Line index |
| `remarks` | `text` | YES | Item notes |
| `created_at` / `updated_at`| `timestamp` | YES | Timestamps |

### Table 3: `receiving_sessions`
| Column | Type | Nullable | Description / Business Purpose |
|---|---|---|---|
| `id` | `bigint unsigned` | NO | Primary Key |
| `warehouse_id` | `bigint unsigned` | NO | FK to `warehouses.id` |
| `outstanding_purchase_order_id`| `bigint unsigned`| NO | FK to `outstanding_purchase_orders.id` |
| `status` | `varchar(255)` | NO | `DRAFT`, `READY_REVIEW`, `REVIEWED`, `COMPLETED`, `CANCELLED` |
| `created_by` | `bigint unsigned` | NO | FK to `users.id` (Operator / Checker) |
| `reviewed_by` | `bigint unsigned` | YES | FK to `users.id` (Reviewer / Supervisor) |
| `started_at` | `timestamp` | NO | Session start timestamp |
| `reviewed_at` | `timestamp` | YES | Review confirmation timestamp |
| `completed_at` | `timestamp` | YES | Receiving finalization timestamp |
| `remarks` | `text` | YES | Physical receiving notes |
| `pdf_path` | `varchar(255)` | YES | Path to generated A4 PDF file |
| `created_at` / `updated_at`| `timestamp` | YES | Timestamps |

### Table 4: `receiving_session_items`
| Column | Type | Nullable | Description / Business Purpose |
|---|---|---|---|
| `id` | `bigint unsigned` | NO | Primary Key |
| `receiving_session_id` | `bigint unsigned` | NO | FK to `receiving_sessions.id` |
| `outstanding_purchase_order_item_id`| `bigint unsigned`| NO | FK to `outstanding_purchase_order_items.id` |
| `item_variant_id` | `bigint unsigned` | YES | FK to `item_variants.id` |
| `expected_qty` | `int` | NO | **Integer** snapshot of PO quantity |
| `received_qty` | `int` | NO | **Integer** quantity accepted during session |
| `verification_status` | `varchar(255)` | NO | `PENDING`, `VERIFIED`, `REMOVED` |
| `removed_reason` | `varchar(255)` | YES | `WRONG WAREHOUSE`, `IMPORTED BY MISTAKE`, `CANCELLED`, `OTHER` |
| `remarks` | `text` | YES | Discrepancy / line remarks |
| `created_at` / `updated_at`| `timestamp` | YES | Timestamps |

### Table 5: `receiving_signatures`
| Column | Type | Nullable | Description / Business Purpose |
|---|---|---|---|
| `id` | `bigint unsigned` | NO | Primary Key |
| `receiving_session_id` | `bigint unsigned` | NO | FK to `receiving_sessions.id` |
| `role` | `varchar(255)` | NO | `DISERAHKAN_OLEH`, `DITERIMA_OLEH`, `BAG_GUDANG` |
| `signature_path` | `varchar(255)` | NO | File path on `public` storage disk |
| `signed_by` | `bigint unsigned` | YES | FK to `users.id` |
| `signed_at` | `timestamp` | NO | Timestamp of signing |
| `created_at` / `updated_at`| `timestamp` | YES | Timestamps (Unique: `session_id` + `role`) |

---

## 6. Existing PO Import Mechanism

- **Files:** `app/Livewire/OutstandingPurchase/OutstandingPurchaseImportPage.php`, `app/Services/OutstandingPurchase/ImportPipelineService.php`, `app/Imports/OutstandingPurchaseOrderImport.php`.
- **Import Methods:**
  1. **Excel Upload (`.xlsx`, `.xls`, `.csv`):** Processed via Maatwebsite Excel using `OutstandingPurchaseOrderImport`.
  2. **Clipboard Paste (Handsontable):** Ingests raw tabular data copied directly from ERP spreadsheets.
- **Variant Resolution:** Uses `ItemVariant::resolveVariant($erpCode, $itemName)` to automatically associate the PO line item with the master warehouse catalog variant if present.
- **Auto-Archiving Behavior (`ImportPipelineService.php:158-162`):**
  ```php
  OutstandingPurchaseOrder::forActiveWarehouse()
      ->whereIn('status', [OutstandingPurchaseOrder::STATUS_PENDING, OutstandingPurchaseOrder::STATUS_PARTIAL])
      ->whereNotIn('po_number', $uniqueProcessedPos)
      ->update(['is_archived' => true]);
  ```
  *Audit Note on Risk:* When a user pastes or uploads a subset of POs, any unmentioned pending/partial PO in the warehouse is automatically marked `is_archived = true`.

---

## 7. Existing Outstanding Calculation

- **Field Level:** `OutstandingPurchaseOrderItem::getPendingQtyAttribute()`
  $$\text{pending\_qty} = \max(0, \text{ordered\_qty} - \text{received\_qty})$$
- **PO Level Status Lifecycle:** Recalculated automatically on `saved` and `deleted` model events via `OutstandingPurchaseOrder::recalculateStatus()`:
  - If all lines have `received_qty == 0` $\rightarrow$ `STATUS_PENDING` (1)
  - If all lines have `pending_qty == 0` $\rightarrow$ `STATUS_CLOSED` (3)
  - Otherwise $\rightarrow$ `STATUS_PARTIAL` (2)

---

## 8. Existing Receiving Capability

- **Session-Based Receiving:** Yes, receiving is isolated into discrete `ReceivingSession` instances linked to the parent PO.
- **Stock Movement Trigger:** On finalization, `InventoryService::moveStock()` is invoked, directly crediting the physical bin associated with the item variant in the active warehouse.
- **PO Line Updates:** Each received line adds to `OutstandingPurchaseOrderItem::received_qty`.

---

## 9. Existing Inspection Capability

- **Item-by-item Checking:** Supported via `PENDING` $\rightarrow$ `VERIFIED` toggle on `ReceivingSessionItem`.
- **Discrepancy Removal:** Line removal modal captures reasons (`WRONG WAREHOUSE`, `IMPORTED BY MISTAKE`, `CANCELLED`, `OTHER`) and notes.
- **Gaps Identified:**
  - **No `HASIL PENGECEKAN` condition field:** There is no field for recording physical condition (`OK`, `RUSAK`, `REJECT`, `SESUAI`).
  - **No `QTY DATANG` separate from `QTY TERIMA`:** Only a single `received_qty` is captured during verification.

---

## 10. Existing Signature Capability

- **Technology:** Uses Alpine.js modal wrapper around `signature_pad@4.1.7` (loaded from CDN).
- **Storage:** Stores Base64-decoded PNG images in `storage/app/public/signatures/`.
- **Roles Defined:** `DISERAHKAN_OLEH`, `DITERIMA_OLEH`, `BAG_GUDANG`.
- **Gap Identified:** Finalization **strictly forces** all 3 signatures. It cannot be bypassed or skipped for manual physical signing.

---

## 11. Existing PDF/A4 Capability

- **Renderer:** `Barryvdh\DomPDF\Facade\Pdf` (DomPDF).
- **View Template:** `resources/views/reports/receiving-inspection-pdf.blade.php`.
- **Form Layout:** Implements ISO document standard format `FR/GUD/10-01-05/17-00-1/1` for **"BUKTI PENGECEKAN BARANG DATANG"**.
- **Embedded Signatures:** Encodes signature images to base64 data URIs and embeds them in the 3 signature columns (`DISERAHKAN OLEH`, `DITERIMA/DICEK OLEH`, `BAG. GUDANG`).
- **Gaps Identified:**
  - Column 4 (`QTY DATANG`) displays `$item->expected_qty` (PO ordered qty).
  - Column 6 (`HASIL PENGECEKAN`) displays mathematical variance (`+1`, `-1`, or blank) rather than physical condition.
  - Column 7 (`DEPT. PEMESAN`) is hardcoded blank (`&nbsp;`).

---

## 12. Android / Mobile Capability

- **Mobile Viewport Support:** Built with Tailwind CSS responsive classes (`p-4`, `min-h-screen`, `pb-24`).
- **Touch Targets:** Large counter buttons (`w-11 h-11`, active touch scaling) for increment/decrement.
- **Canvas Signature on Touchscreen:** Fully configured with `touch-none` and responsive canvas resize for mobile screens.
- **Bottom Fixed Action Bar:** Bottom bar (`fixed bottom-0 left-0 right-0`) provides thumb-friendly access to action triggers.

---

## 13. Workflow Gap Analysis

| Feature Area | Current Implementation | Desired Business Requirement | Gap Severity |
|---|---|---|---|
| **Delivered vs Accepted Qty** | Single `received_qty` input. `expected_qty` shown as PO quantity. | Physical delivery note may state 10, but checker accepts 8. Both `QTY DATANG` (10) and `QTY TERIMA` (8) must be recorded. | **HIGH** |
| **Physical Inspection Result** | Variance difference calculated mathematically. No qualitative field. | Checker inspects quality and records result (e.g. `OK`, `REJECT 2 PCS PECAH`, `SESUAI`). | **HIGH** |
| **Ordering Department** | Hardcoded empty in PDF. Not present in PO tables. | Printed form requires `DEPT. PEMESAN` (e.g., PPIC, Maintenance). | **MEDIUM** |
| **Decimal / Fractional Qty** | Strict integer columns in MySQL and integer casts in PHP. | Must support fractional units (e.g., `0.8 KG`, `2.5 MTR`). | **CRITICAL** |
| **Digital Signature Enforcement** | Hardcoded requirement for 3 digital signatures before commit. | Digital signature must be optional/non-blocking so physical signing can be used after print. | **HIGH** |
| **Subsequent Partial Receiving Sessions** | `OutstandingPurchaseShowPage` snapshots original `ordered_qty` and completed items into new sessions. | New receiving session must snapshot remaining `pending_qty` and only load uncompleted lines. | **HIGH** |
| **Offline / Spotty Warehouse Wi-Fi** | Livewire server-side roundtrips on every button click. | Basic offline resilience or optimistic UI updates on Android. | **LOW / UX** |

---

## 14. Business Requirement $\rightarrow$ Existing System Matrix

| # | Requirement | Current System | Status | Evidence | Gap |
|---|---|---|---|---|---|
| 1 | Capture PO from ERP | Import via Excel & Clipboard grid | **PASS** | `ImportPipelineService.php:86-150` | None |
| 2 | Do not overwrite original PO | Uses separate `receiving_sessions` & `receiving_session_items` | **PASS** | `ReceivingSessionItem.php` | Original PO preserved |
| 3 | Mobile / Android usability | Responsive layout, touch buttons, canvas pad | **PASS** | `receiving-session-page.blade.php:260-285` | Touch UX already designed |
| 4 | Item-by-item verification | Checker toggles item to `VERIFIED` or `REMOVED` | **PASS** | `ReceivingSessionPage.php:105-121` | Item checklist functional |
| 5 | Record delivered quantity (`QTY DATANG`) | Only stores `expected_qty` (from PO) | **FAIL** | `ReceivingSessionItem.php:21-22` | No `qty_datang` column |
| 6 | Record accepted quantity (`QTY TERIMA`) | `received_qty` captured | **PASS** | `ReceivingSessionPage.php:89-103` | Functional |
| 7 | Record check result (`HASIL PENGECEKAN`) | Computed variance (+ / -) in PDF | **FAIL** | `receiving-inspection-pdf.blade.php:200-206` | No text/status result field |
| 8 | Decimal quantity support (e.g. 0.8 KG) | Schema uses `int`, casts `integer` | **FAIL** | `2026_08_08_000001_create_receiving_sessions_table.php:36-37` | Truncates decimals to integers |
| 9 | Optional digital signature | Required: throws exception if `< 3` | **FAIL** | `ReceivingSessionPage.php:349-351` | Hard-blocks saving without signatures |
| 10 | Ordering department (`DEPT. PEMESAN`) | Empty placeholder (`&nbsp;`) in PDF | **FAIL** | `receiving-inspection-pdf.blade.php:208` | Field omitted from data pipeline |
| 11 | Generate A4 Bukti Pengecekan | DomPDF template matching ISO form | **PARTIAL** | `receiving-inspection-pdf.blade.php` | Layout exists but misses data fields |
| 12 | Safe Stock ledger integration | Updates WMS Bin and `stock_movements` | **PASS** | `InventoryService.php:14-60` | Atomic stock updates |

---

## 15. A4 Form Field Mapping

| A4 Form Field | Physical Form Meaning | Current Data Source | Model / Table | Column Name | Available? |
|---|---|---|---|---|---|
| **Header: Company** | PT. PERONI KARYA SENTRA | Static in Blade | Hardcoded | N/A | **AVAILABLE** |
| **Header: Form Number** | FR/GUD/10-01-05/17-00-1/1 | Static in Blade | Hardcoded | N/A | **AVAILABLE** |
| **No. PO** | Purchase Order Number | `$session->outstandingPurchaseOrder->po_number` | `outstanding_purchase_orders` | `po_number` | **AVAILABLE** |
| **Tanggal Datang** | Physical Arrival Date | `$session->completed_at` | `receiving_sessions` | `completed_at` | **AVAILABLE** |
| **Supplier** | Vendor Name | `$session->outstandingPurchaseOrder->supplier_name_snapshot` | `outstanding_purchase_orders` | `supplier_name_snapshot` | **AVAILABLE** |
| **Warehouse** | Warehouse Name | `$session->warehouse->name` | `warehouses` | `name` | **AVAILABLE** |
| **Operator / Checker**| Person Checking Goods | `$session->creator->name` | `users` | `name` | **AVAILABLE** |
| **Table: NO** | Line Index (1, 2, 3...) | Dynamic counter in Blade loop | N/A | N/A | **AVAILABLE** |
| **Table: KODE** | Part / ERP Item Code | `$item->variant->erp_code` | `item_variants` / `outstanding_purchase_order_items` | `erp_code` | **AVAILABLE** |
| **Table: NAMA BARANG**| Item Name Description | `$item->outstandingPurchaseOrderItem->item_name_snapshot` | `outstanding_purchase_order_items` | `item_name_snapshot` | **AVAILABLE** |
| **Table: QTY DATANG** | Quantity physically delivered | *Incorrectly maps `$item->expected_qty` (PO Qty)* | `receiving_session_items` | *None* | **MISSING** |
| **Table: QTY TERIMA** | Quantity accepted into stock | `$item->received_qty` | `receiving_session_items` | `received_qty` | **AVAILABLE** |
| **Table: HASIL PENGECEKAN**| Inspection condition (OK/Reject/etc.) | *Incorrectly calculates variance (+/-)* | `receiving_session_items` | *None* | **MISSING** |
| **Table: DEPT. PEMESAN**| Ordering Department | *Hardcoded blank `&nbsp;`* | `outstanding_purchase_orders` / `departments` | *None* | **MISSING** |
| **Sig 1: Diserahkan Oleh**| Supplier / Driver Signature | `$signatures['DISERAHKAN_OLEH']` | `receiving_signatures` | `signature_path` | **AVAILABLE** |
| **Sig 2: Diterima Oleh**| Checker Signature | `$signatures['DITERIMA_OLEH']` | `receiving_signatures` | `signature_path` | **AVAILABLE** |
| **Sig 3: Bag. Gudang** | Warehouse Staff Signature | `$signatures['BAG_GUDANG']` | `receiving_signatures` | `signature_path` | **AVAILABLE** |

---

## 16. Desired Workflow

```
1. Supplier Arrives at Loading Dock with Goods & Surat Jalan
   │
2. Receiving Personnel opens Android phone -> /outstanding-purchases
   │
3. Selects the arrived PO -> Clicks "START RECEIVING / CHECKING"
   │
4. System presents PO line items on Mobile Screen:
   - Item Code & Name
   - Expected Quantity (PO Remaining)
   - Input: QTY DATANG (Delivered Quantity per Surat Jalan)
   - Input: QTY TERIMA (Physically Accepted Quantity)
   - Input: HASIL PENGECEKAN (e.g., OK / Cacat / Dimensi Beda)
   │
5. Checker inspects physical goods item-by-item:
   - Ticks checkmark [✓]
   - Enters delivered & accepted quantities (supports decimals e.g. 0.8 KG)
   - Selects / types check result
   │
6. Checker saves inspection:
   - OPTIONAL: Driver signs on Android touchscreen.
   - If driver cannot/will not sign digitally: Checker saves transaction anyway.
   │
7. System updates WMS stock immediately based on QTY TERIMA.
   │
8. Later, Admin Sparepart / Gudang opens record and prints A4:
   "BUKTI PENGECEKAN BARANG DATANG"
   - If digitally signed: Signatures appear printed on the document.
   - If not digitally signed: Signature boxes are empty for physical pen signing.
```

---

## 17. Current Workflow vs Desired Workflow

```
CURRENT WORKFLOW:
PO -> Show PO -> Start Session (clones all PO items with expected_qty=ordered_qty)
   -> Counter (+/-) adjusts single received_qty
   -> Must mark missing items as REMOVED
   -> Complete Verification -> Review & Confirm
   -> FORCED 3 Digital Signatures (Cannot finalize without all 3!)
   -> Finalize (updates stock & generates PDF with missing check results & missing dept)

DESIRED WORKFLOW:
PO -> Show PO -> Start Session (clones only pending lines with expected_qty=pending_qty)
   -> Checker captures QTY DATANG, QTY TERIMA, and HASIL PENGECEKAN per item
   -> Supports decimal units (KG, MTR, LTR)
   -> Verification completed
   -> OPTIONAL Digital Signature (recommended, but non-blocking)
   -> Finalize (updates stock immediately from QTY TERIMA)
   -> Print authentic A4 "BUKTI PENGECEKAN BARANG DATANG" (with complete check result & dept data)
```

---

## 18. State Machine Analysis

```
                    ┌─────────────────────────┐
                    │          DRAFT          │ ◄── Checker counts & verifies lines
                    └────────────┬────────────┘
                                 │ completeVerification() [All items resolved]
                                 ▼
                    ┌─────────────────────────┐
                    │      READY_REVIEW       │ ◄── Verification complete
                    └────────────┬────────────┘
                                 │ reviewAndConfirm()
                                 ▼
                    ┌─────────────────────────┐
                    │        REVIEWED         │ ◄── Ready for optional signature
                    └────────────┬────────────┘
                                 │ finalizeReceiving()
                                 ▼
                    ┌─────────────────────────┐
                    │        COMPLETED        │ ◄── Stock updated, PDF generated
                    └─────────────────────────┘
```
- **State Transition Permissions:** Currently, any authenticated warehouse operator can trigger transitions.
- **Reprinting:** Completed sessions can have their PDF reprinted at any time via `ReceivingPdfController::view()`.

---

## 19. Partial Receiving Analysis

### Scenario: PO ordered 10 KG AMS90. Supplier delivers 6 KG today. 4 KG delivered next week.
- **Session 1 (Delivery of 6 KG):**
  - Expected: 10 KG. Received: 6 KG.
  - Finalization commits 6 KG to stock.
  - `OutstandingPurchaseOrderItem::received_qty` becomes 6. `pending_qty` becomes 4.
  - `OutstandingPurchaseOrder` status becomes `STATUS_PARTIAL` (2).
- **Session 2 (Delivery of 4 KG - CURRENT DEFECT):**
  - `OutstandingPurchaseShowPage::startReceivingSession()` executes:
    `'expected_qty' => $poItem->ordered_qty` (snapshots 10 instead of 4).
  - If the PO had 5 items and 4 were already completed in Session 1, all 5 items are still created in Session 2 as `PENDING`, forcing the operator to mark completed items as `REMOVED`.
- **Architectural Solution Required:** New receiving sessions must only include items where `pending_qty > 0`, and must snapshot `expected_qty = $poItem->pending_qty`.

---

## 20. Multi-Item PO Analysis

- **Integrity Principle:** **1 Receiving Document = 1 Physical Delivery Event = N PO Items.**
- **Current Support:** **SUPPORTED.** `ReceivingSession` represents 1 receiving event and has a `hasMany` relationship with `ReceivingSessionItem`. Multiple PO line items are bundled into a single receiving session and printed onto a single A4 document.

---

## 21. Security / Integrity Risks

1. **Integer Quantities Overwriting Decimals:**
   - Entering `0.8` on an integer column truncates to `0`. Entering `1.5` truncates to `1`.
   - *Risk:* Severe inventory count drift for weight-based/liquid spareparts.
2. **Double Finalization Race Conditions:**
   - Currently mitigated with `DB::transaction()` and `lockForUpdate()` on `ReceivingSession` and `Bin`.
3. **Auto-Archive Ingestion Hazard:**
   - In `ImportPipelineService.php`, importing an Excel sheet containing only 1 PO auto-archives all other pending POs in that warehouse.
   - *Risk:* Active POs vanish from operator's active receiving list.
4. **Signature Blocking Lockout:**
   - Operators on the floor cannot complete transactions if the delivery driver refuses to sign the touchscreen.

---

## 22. Warehouse Isolation Analysis

- **Scope Implementation:** `OutstandingPurchaseOrder::forActiveWarehouse()` and `ReceivingSession::scopeForActiveWarehouse()`.
- **Strict Mode Enforcement:** Adheres to `env('WMS_GOVERNANCE_STRICT_MODE', true)`. If `active_warehouse_id` is missing in session, queries fail-safe to `1 = 0`.
- **Runtime Verification:** `ReceivingSessionPage::mount()` and `ReceivingSessionPage::finalizeReceiving()` explicitly verify `$session->warehouse_id == session('active_warehouse_id')` and abort with `403` on mismatch.
- **Verdict:** Warehouse isolation is **properly maintained**.

---

## 23. Required Changes Classification

| Component | Target Location | Change Category | Description |
|---|---|---|---|
| **Delivered Quantity** | `receiving_session_items` | **New database column** | Add `qty_datang` (`decimal(10,3)` or `double`) |
| **Accepted Quantity** | `receiving_session_items` | **Modify database column** | Change `received_qty` and `expected_qty` from `int` to `decimal(10,3)` |
| **PO Quantities** | `outstanding_purchase_order_items` | **Modify database column** | Change `ordered_qty` and `received_qty` from `int` to `decimal(10,3)` |
| **Inspection Result** | `receiving_session_items` | **New database column** | Add `check_result` (`varchar(50)`) & `check_notes` (`text`) |
| **Ordering Department** | `outstanding_purchase_orders` / `items` | **New database column** | Add `department_name` or `department_id` |
| **Optional Signatures** | `ReceivingSessionPage.php` | **Livewire/PHP logic** | Remove `< 3` signature validation check in `finalizeReceiving()` |
| **Partial Snapshotting**| `OutstandingPurchaseShowPage.php` | **Livewire/PHP logic** | Filter `pending_qty > 0` and set `expected_qty = pending_qty` |
| **Android UI Checklist** | `receiving-session-page.blade.php` | **Blade/UI only** | Add inputs for `qty_datang`, `qty_terima`, and `hasil_pengecekan` dropdown |
| **A4 PDF Document** | `receiving-inspection-pdf.blade.php` | **Blade/UI only** | Map `qty_datang`, `qty_terima`, `check_result`, and `department_name` |

---

## 24. Migration Requirement

### Can the complete desired workflow be implemented without a database migration?

### **Answer: NO**

### Technical Justification:
1. **Decimal Precision is Required:** The current MySQL schema defines `ordered_qty`, `received_qty`, and `expected_qty` as `INTEGER`. Handling physical quantities such as `0.8 KG`, `2.5 LTR`, or `1.75 MTR` cannot be achieved without altering column definitions to `decimal(10,3)`.
2. **Missing `qty_datang` Column:** There is currently only one receiving quantity column (`received_qty`). Capturing both the supplier delivery note quantity (`QTY DATANG`) and the warehouse accepted quantity (`QTY TERIMA`) requires a new column on `receiving_session_items`.
3. **Missing `check_result` Column:** Storing the qualitative physical inspection condition (`OK`, `REJECT`, `RUSAK`) requires a dedicated column on `receiving_session_items`.
4. **Missing `department_name` / `department_id` Column:** Recording the ordering department on PO headers or items requires schema extension to populate `DEPT. PEMESAN` on the A4 form.

---

## 25. Recommended Implementation Phases

*(Note: Proposals only. Not implemented during this audit.)*

- **PHASE 1: Schema Migration & Model Hardening**
  - Alter quantity columns in `outstanding_purchase_order_items` and `receiving_session_items` to `decimal(10,3)`.
  - Add `qty_datang`, `check_result`, and `check_notes` to `receiving_session_items`.
  - Add `department_name` to `outstanding_purchase_orders` / `outstanding_purchase_order_items`.
- **PHASE 2: PO Ingestion & Partial Session Snapshotting**
  - Update `ImportPipelineService` to ingest department data if available.
  - Fix `OutstandingPurchaseShowPage::startReceivingSession()` to snapshot remaining `pending_qty` and omit closed items.
- **PHASE 3: Mobile / Android Receiving & Checking UI**
  - Update `ReceivingSessionPage` with touch-friendly fields for `QTY DATANG`, `QTY TERIMA`, and `HASIL PENGECEKAN` (e.g. Quick buttons: `OK`, `REJECT`, `DEFECT`).
- **PHASE 4: Non-Blocking Optional Digital Signature**
  - Update `ReceivingSessionPage::finalizeReceiving()` to make digital signatures optional.
- **PHASE 5: A4 "BUKTI PENGECEKAN BARANG DATANG" PDF Alignment**
  - Update `receiving-inspection-pdf.blade.php` to accurately map all columns to the physical form layout.
- **PHASE 6: End-to-End Automated Testing & UAT**
  - Unit and feature tests covering fractional receiving, partial PO sessions, optional signatures, and PDF generation.

---

## 26. Final Verdict

### Does the CURRENT `/outstanding-purchases` implementation already support the desired workflow?

### **Verdict: PARTIALLY READY**

### Key Blockers Preventing Immediate Production Use:
1. **Mandatory Signature Constraint:** Hard-coded check strictly blocks saving receiving transactions if any of the 3 signatures is missing.
2. **Integer Truncation:** Decimals (e.g., 0.8 KG) are truncated to integers.
3. **Missing Inspection Fields:** No fields for `QTY DATANG` vs `QTY TERIMA`, `HASIL PENGECEKAN`, or `DEPT. PEMESAN`.
4. **Partial PO Snapshot Defect:** Starting subsequent sessions snapshots initial PO `ordered_qty` rather than remaining `pending_qty`.

---

## 27. Most Important Question

### Question:
> *"Can the current system produce the desired A4 BUKTI PENGECEKAN BARANG DATANG accurately from data captured by the person physically checking the goods on Android, without requiring Admin Sparepart to reconstruct the form afterward?"*

### **Answer: PARTIALLY**

### Detailed Explanation:
1. **What Works:** The infrastructure to initiate receiving on Android, adjust item verification status, commit stock to bins, and render an A4 PDF document using DomPDF **already exists and functions end-to-end**.
2. **Why It Is Not Fully Accurate Yet:**
   - The printed `QTY DATANG` currently prints the original PO ordered quantity, not what the delivery person physically brought.
   - The printed `HASIL PENGECEKAN` currently prints a mathematical difference (`+ / -`), not the actual condition checked by the operator.
   - The printed `DEPT. PEMESAN` is completely empty.
   - If the delivery driver does not sign on Android, the system prevents finalizing the record altogether, forcing staff to abandon the digital workflow.
3. **Conclusion:** Once the schema is augmented with the missing inspection fields, decimal support is enabled, and the signature blocking check is made optional, the system will fully achieve the target business goal without requiring Admin Sparepart to reconstruct physical inspection forms.
