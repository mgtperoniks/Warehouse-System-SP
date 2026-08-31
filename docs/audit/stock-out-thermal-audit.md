# WMS Stock Out Thermal Receipt Integration Audit (Xantri 58 mm Bluetooth)

**Document Status:** Permanent Architecture Audit  
**Date:** August 31, 2026  
**Scope:** WMS `/scan` Stock-Out Workflow, PDF/Print Subsystem, Android Compatibility, and Xantri 58 mm Bluetooth Thermal Printing Integration.  
**Auditor:** DeepMind Antigravity Pair Programmer  

---

## 1. Executive Summary & Readiness Verdict

### **VERDICT: READY FOR IMPLEMENTATION**

The WMS stock-out subsystem is architecturally mature, decoupled, and strictly idempotent. Stock movements are completely separated from presentation rendering. The underlying domain models (`StockTransaction`, `StockTransactionItem`, and `StockMovement`) already capture complete point-in-time snapshots of every checkout. 

Implementing direct 58 mm Bluetooth thermal receipt printing can be accomplished with **zero changes to the core inventory ledger**, **zero schema migrations**, and **zero alterations to stock deduction logic**.

---

## 2. Current Stock-Out Architecture

The stock-out workflow is managed by the `/scan` terminal page, which serves both fixed desktop workstations (with USB/HID wedge barcode scanners) and mobile Android smartphones (with built-in camera scanning via `Html5Qrcode` / `PeroniksCameraScanner`).

```
[Android Smartphone / Desktop Browser]
                   │
                   ▼  GET /scan
        [ScanController::index()]
                   │
                   ▼  Renders
         [ScanPage (Livewire 3)]
   ├── State: $cart, $deptId, $picId, $reference
   └── Inputs: Wedged Scanner / Manual / Peroniks Camera
                   │
                   ▼  User clicks "Submit Checkout"
        [ScanPage::submit(InventoryService)]
   ├── Atomic Cache Lock: `stock-out-lock-{user_id}` (10s)
   ├── Validate Cart, Department, PIC, Available Bins
   └── DB::transaction (ACID)
          ├── 1. Sort allocations by bin_id ASC (Deadlock Prevention)
          ├── 2. Generate code: StockTransaction::generateCode()
          │      └── Format: OUT-YYYY-MM-DD-XXXX (e.g. OUT-2026-08-31-0001)
          ├── 3. Create StockTransaction (Header, status=CONFIRMED)
          ├── 4. For each allocation:
          │      ├── InventoryService::moveStock(Bin, qty, 'OUT', trx.code)
          │      │   ├── Row-level Lock (SELECT ... FOR UPDATE on `bins`)
          │      │   ├── Create StockMovement ledger entry
          │      │   └── Bin balance deduction ($bin->current_qty -= $qty)
          │      └── Create StockTransactionItem (snapshot of name, erp, price)
          └── 5. session()->forget('scan_cart')
                   │
                   ▼  Commit & Redirect
[GET /reports/stock-out/preview?code=OUT-2026-08-31-0001]
   ├── ReportController::previewStockOut()
   ├── resources/views/reports/stock-out-preview.blade.php
   └── Interactive Options:
          ├── [Print Receipt] ──► window.open('/reports/stock-out/print?code=...')
          └── [New Session]   ──► Redirect to /scan
```

---

## 3. Exact Code Call Chain

| Step | Component / File | Method / Action | Detail |
| :--- | :--- | :--- | :--- |
| **1. Ingestion** | `resources/views/livewire/scan/scan-page.blade.php`<br>`public/assets/js/peroniksscanner.js` | Hardware wedge `keydown` or `PeroniksCameraScanner.start()` | Dispatches scan text to Livewire |
| **2. Parse & Cart** | `app/Livewire/Scan/ScanPage.php` | `submitScan($barcode, $qty)`<br>`addToCartDirect($qty)` | Resolves `ItemBarcode`, validates stock against `Bin::where('item_variant_id', ...)->sum('current_qty')`, saves to `session('scan_cart')`. |
| **3. Checkout** | `app/Livewire/Scan/ScanPage.php` | `submit(InventoryService $inventoryService)` | Acquires `Cache::lock('stock-out-lock-'.auth()->id(), 10)`. Initiates `DB::transaction`. |
| **4. Code Gen** | `app/Models/StockTransaction.php` | `StockTransaction::generateCode()` | Queries last prefix `OUT-YYYY-MM-DD-` to determine next 4-digit sequential integer. |
| **5. Header Creation** | `app/Models/StockTransaction.php` | `StockTransaction::create([...])` | Saves header with status `'CONFIRMED'`, `department_id`, `user_id` (PIC), `operator_id`, `total_price`, etc. |
| **6. Ledger & Stock** | `app/Services/Inventory/InventoryService.php` | `moveStock($bin, $qty, 'OUT', $trxCode, $userId)` | Obtains `lockForUpdate()` on bin; inserts `StockMovement` with type `'OUT'`; subtracts `$bin->current_qty`. |
| **7. Line Snapshots**| `app/Models/StockTransactionItem.php` | `StockTransactionItem::create([...])` | Persists frozen snapshots: `item_name_snapshot`, `erp_code_snapshot`, `unit_snapshot`, `price_snapshot`, `total_price_snapshot`. |
| **8. Session Clear** | `app/Livewire/Scan/ScanPage.php` | `session()->forget('scan_cart')` | Empties active cart. |
| **9. Post-Trx Routing**| `app/Livewire/Scan/ScanPage.php` | `return redirect()->route('reports.stock-out.preview', ['code' => $transaction->code])` | Clean browser navigation to transaction confirmation preview. |
| **10. Print Trigger**| `resources/views/reports/stock-out-preview.blade.php` | `printThermal()` -> `window.open(...)` | Opens `/reports/stock-out/print?code=OUT-...` which renders `reports/stock-out-print.blade.php`. |

---

## 4. Current PDF / Print Receipt Architecture

### Identification:
- **Renderer:** Currently implemented as an inline HTML/CSS thermal emulation view at `resources/views/reports/stock-out-print.blade.php` served by `ReportController::printStockOut()`.
- **Styling:** Uses `@page { size: 58mm 200mm; margin: 0; }` with CSS Courier New typography.
- **Trigger:** JavaScript executes `window.print()` upon `window.onload`.
- **Timing:** Strictly **C. AFTER SUCCESSFUL STOCK MOVEMENT** (the controller only renders committed database records from `StockTransaction::with(...)`).

### Current Field Mapping:
| Receipt Field | Current Source in Database | Field Status on 58 mm Paper |
| :--- | :--- | :--- |
| **Header Title** | Static: `"SPAREPART WAREHOUSE"` | Fits centered (19 chars) |
| **Subheader** | Static: `"STOCK OUT RECEIPT"` | Fits centered (17 chars) |
| **Transaction Code** | `$tx->code` (e.g. `OUT-2026-08-31-0001`) | Fits (25 chars) |
| **Timestamp** | `$tx->created_at->format('d/m/Y H:i')` | Fits (16 chars) |
| **Operator (Warehouse)** | `$tx->operator->name` (fallback `auth()->user()->name`) | Fits (e.g. `OP  : JOHN DOE`) |
| **Department** | `$tx->department->name` | Fits (e.g. `DEPT: PPIC`) |
| **Receiver / PIC** | `$tx->user->name` | Stored in DB; omitted in current thermal CSS, present in preview |
| **Reference** | `$tx->reference` | Optional; fits on single line if short |
| **Line Items** | `$tx->items` (`item_name_snapshot`, `erp_code_snapshot`, `qty`, `unit_snapshot`) | Multi-line formatted |
| **Footer Status** | Static: `"RECEIVED SUCCESSFULLY"` | Fits centered (21 chars) |

---

## 5. Transaction Identity & Canonical Numbering

- **Canonical Identifier:** `OUT-YYYY-MM-DD-XXXX` (Example: `OUT-2026-08-31-0001`).
- **Generation Logic:** Located in `App\Models\StockTransaction::generateCode()`.
- **Database Storage:** Stored as `stock_transactions.code` (unique indexed string).
- **Ledger Reference:** Propagated to `stock_movements.reference`.
- **Audit Rule:** The thermal receipt and the official report/PDF **MUST NEVER** generate a separate receipt numbering system. Both renderers strictly consume `$tx->code`.

---

## 6. Database Structure & Item Multiplicity

### Schema Entities Involved:
1. `stock_transactions` (Header): 1 row per checkout.
2. `stock_transaction_items` (Line items): 1..N rows per checkout.
3. `stock_movements` (Immutable inventory ledger): 1..N rows per checkout (1 per bin deduction).
4. `bins` (Inventory balances): Updated in place.
5. `item_variants` & `items` (Master catalog): Referenced for lookups.
6. `departments` & `users` (Organizational relations): Department and PIC receivers.

### Multiplicity Finding:
> **One stock-out transaction can contain MULTIPLE ITEMS (1 to N items).**  
> Furthermore, a single item line requested in quantity 10 may be fulfilled across two separate physical bins (e.g., 6 from Bin A-01 and 4 from Bin A-02).  
> The thermal receipt layout **MUST** support variable-length item tables that dynamically grow and feed paper appropriately.

---

## 7. Android & Mobile Compatibility Findings

### Hardware & Platform Profile:
- **Target Printer:** Xantri 58 mm Bluetooth Thermal Printer (ESC/POS compatible, 384 dots/line, 32 standard characters per line).
- **Client Device:** Android Smartphone running Google Chrome / Chromium Mobile.
- **WMS App Context:** Web application running over HTTP/HTTPS.

### Mobile Browser Limitations with Current `window.print()`:
1. **System Print Dialog Friction:** `window.print()` on Android opens the Android OS Print Spooler. It attempts to scale the page to A4 or requires a vendor-specific Print Service plugin.
2. **Popup Blockers:** `window.open()` invoked asynchronously after form submission is frequently blocked by Android Chrome unless initiated directly by a user tap event.
3. **No Direct ESC/POS Command Control:** Standard browser printing cannot send raw hardware cut commands (`GS V 66 0`), hardware line feeds, or native thermal font density settings.

---

## 8. Bluetooth Printing Architecture Evaluation

| Architecture Option | Feasibility on Android Chrome | User Experience | Dependencies / Prerequisites | Recommendation |
| :--- | :--- | :--- | :--- | :--- |
| **Option A: Web Bluetooth API (Direct Browser-to-Printer)** | **HIGH** (Supported in Android Chrome) | ⭐⭐⭐⭐⭐ **Best**<br>Direct 1-tap print directly from `/scan` or preview page. No 3rd party apps needed. | 1. HTTPS connection (Chrome requirement for Web Bluetooth).<br>2. Printer supports BLE GATT service (Standard on modern Bluetooth 4.0/5.0 POS printers). | **PRIMARY RECOMMENDED** |
| **Option B: Android Native Print Helper Intent (RawBT)** | **HIGH** (Universal for all Bluetooth types) | ⭐⭐⭐⭐ **High**<br>Web redirects via `rawbt:data` or Android Intent; RawBT handles classic Bluetooth SPP / RFCOMM. | Requires free/standard RawBT app installed once on Android device. | **SEAMLESS FALLBACK** (For Classic BT 2.0 / Non-BLE) |
| **Option C: Android Native System Print Spooler** | **MEDIUM** (Current mechanism) | ⭐⭐ **Poor**<br>Shows full Android print preview dialog; paper sizing frequently misaligned; requires clicking system print. | None. | **LEGACY / RETIRED** |
| **Option D: Server-Side Network Print Agent** | **NOT APPLICABLE** for Mobile BT | N/A<br>Bluetooth printer is paired directly to operator's mobile phone; Linux server cannot reach phone Bluetooth. | Server queue bridge (`print_jobs`) is designed for stationary USB printers (e.g., TSC TE244), not roaming mobile BT. | **NOT SUITABLE FOR MOBILE** |

---

## 9. Recommended Architectural Design: Dual-Renderer Model

In accordance with architectural principles, the system will **NOT** convert HTML/PDF to bitmap images. Instead, the transaction data is the single source of truth:

```
                  ┌─────────────────────────────────┐
                  │  StockTransaction (Database)    │
                  │  - Code: OUT-2026-08-31-0001    │
                  │  - Snapshots: Items, Qty, PIC   │
                  └────────────────┬────────────────┘
                                   │
              ┌────────────────────┴────────────────────┐
              ▼                                         ▼
   [Official PDF / Web Report]               [Thermal ESC/POS Renderer]
   - Full A4 / Preview Layout               - 32-Column Pure Text & Commands
   - Blade Template                          - Formatted Binary / Byte Stream
   - Document Archival                       - Native ESC/POS Font & Density
   - Desktop / Management                    - Direct Bluetooth 58 mm
```

### 58 mm Thermal Layout Specification (32 Columns Font A):
```text
      SPAREPART WAREHOUSE       <- (Centered, Bold)
       STOCK OUT RECEIPT        <- (Centered)
================================
TRX : OUT-2026-08-31-0001
DATE: 31/08/2026 08:23
OP  : AHMAD FAUZI
DEPT: PPIC
PIC : BUDI SANTOSO
--------------------------------
ITEM / ERP CODE            QTY
--------------------------------
BEARING 6204-2RS NTN
SP-BRG-001               x2 PCS
V-BELT B-52 MBL
SP-BLT-052               x1 PCS
================================
TOTAL ITEMS: 2 (3 PCS)

      RECEIVED SUCCESSFULLY     <- (Centered, Bold)
\n\n\n (Feed 3 lines & Cut)
```

---

## 10. Duplicate Printing & Reprint Safety Audit

### Critical Principle:
> **PRINTING MUST NEVER TRIGGER STOCK MOVEMENT.**

### Safety Guarantees in Current Architecture:
1. **Decoupled Execution:** Stock movement occurs exclusively in `ScanPage::submit()` within `DB::transaction`. Printing is triggered only after the transaction has committed and returned.
2. **Pure Read-Only Operations:** The print endpoint (`ReportController::printStockOut` or a dedicated ESC/POS payload endpoint) executes only `SELECT` queries on `StockTransaction::with(['items', 'department', 'user', 'operator'])`.
3. **Infinite Safe Reprints:** Any transaction can be reprinted immediately after checkout or months later from the Stock Out Report (`/reports/stock-out`) without modifying inventory levels or movement ledgers.

---

## 11. Failure Scenarios & Mitigations

| Failure Scenario | System State | Risk to Inventory | Mitigation / Recovery Procedure |
| :--- | :--- | :--- | :--- |
| **A. Printer Disconnected / Turned Off** | Stock-out committed; user on preview screen. | **ZERO RISK** | Screen displays "Bluetooth Disconnected". Operator powers on printer and taps **"Retry Print"**. |
| **B. Bluetooth Transmission Fails Mid-Print** | Stock-out committed; receipt incomplete. | **ZERO RISK** | Operator taps **"Reprint Receipt"**. Inventory is completely untouched. |
| **C. Android Browser Blocks Bluetooth / Popup** | Stock-out committed; browser warning displayed. | **ZERO RISK** | Dedicated "Connect & Print" button directly within UI requires a standard user gesture. |
| **D. Out of Paper** | Stock-out committed; paper roll empty. | **ZERO RISK** | Operator loads new 58 mm roll, then taps **"Reprint"** on screen. |
| **E. Browser Closed Immediately After Submit** | Stock-out committed. | **ZERO RISK** | Transaction is saved in `stock_transactions`. Operator opens `/reports/stock-out`, clicks transaction code, and prints. |
| **F. Reprinting Old Historical Receipts** | Historical record queried. | **ZERO RISK** | Operator selects any historical record in `/reports/stock-out` and clicks "Print Thermal". |

---

## 12. Scope Boundary & File Impact Analysis

### Files That Would Need Modification (in Implementation Phase):
- `resources/views/reports/stock-out-preview.blade.php`: Add client-side Web Bluetooth / ESC-POS printing handler and pairing button.
- `resources/views/livewire/reports/stock-out-report.blade.php`: Add 1-click thermal reprint button for historical records.
- `public/assets/js/escpos-thermal-printer.js` *(NEW)*: Reusable, lightweight JS library to build ESC/POS byte buffers and transmit over Web Bluetooth / Android Intent.

### Files That MUST NOT Be Modified:
- `app/Services/Inventory/InventoryService.php` ❌ *(Strictly forbidden; core stock ledger must remain untouched)*
- `app/Models/StockMovement.php` ❌ *(Ledger schema and logic must remain untouched)*
- `app/Models/StockTransaction.php` ❌ *(Code generation and relations are already complete)*
- `database/migrations/*` ❌ *(No database changes required)*

---

## 13. Recommended Implementation Phases

```
Phase 1: Pure ESC/POS Builder Module (Client-Side JS)
  └── Implement standard 32-column formatting utility (ESC @, ESC E, GS V).

Phase 2: Web Bluetooth & RawBT Dual-Transport Bridge
  └── Implement navigator.bluetooth connection with fallback to RawBT intent.

Phase 3: UI Integration on Stock Out Preview & Scan Pages
  └── Enhance `stock-out-preview.blade.php` with direct 1-tap thermal print.

Phase 4: Historical Reprint Integration
  └── Add thermal print button in `stock-out-report.blade.php`.

Phase 5: Field Testing & Verification on Xantri 58 mm Hardware
  └── Perform physical print test with single-item, multi-item, and long item name transactions.
```

---

## 14. Testing Strategy

1. **Unit / Parser Test:** Verify ESC/POS byte generator produces exact 32-column aligned lines without overflowing 58 mm printable area.
2. **Idempotency Test:** Verify triggering print 10 consecutive times does not alter `bins.current_qty` or create extra `stock_movements`.
3. **Multi-Item Line Test:** Verify receipt formatting cleanly renders 1 item, 5 items, and items with descriptions exceeding 30 characters.
4. **Android Chrome Physical Hardware Test:** Test Bluetooth pairing, disconnect recovery, out-of-paper recovery, and print speed on the physical Xantri 58 mm printer.
