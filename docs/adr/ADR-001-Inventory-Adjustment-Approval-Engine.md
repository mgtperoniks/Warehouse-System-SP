# ADR-001: Inventory Adjustment Approval Engine

## Status
Accepted

## Date
2026-06-30

## Context
In previous versions of the Warehouse System, when an operator performed a physical stock audit (Stock Opname) and detected a quantity discrepancy (variance), the system immediately adjusted the bin quantity and recorded a stock movement ledger entry.

This direct-adjustment workflow created severe operational and compliance risks:
1. **Lack of Internal Control**: Operators could adjust ledger quantities arbitrarily (potentially masking shrinkage, damage, or theft) without independent verification or managerial oversight.
2. **Data Integrity Risks**: Simultaneous updates without transactional locking created race conditions and potential database inconsistencies.
3. **Audit Trail Gaps**: Historical logs lacked context, such as the warehouse context, operator name snapshots, and structured reasons, making third-party audits difficult.

To satisfy strict industrial governance, a robust **approval gate** is required. Discrepancies must be held in a pending queue as an audit record while the actual bin quantity changes remain uncommitted. However, this gate must not slow down floor operators who require a fast, high-throughput scanning workflow. Therefore, operational scanning is decoupled from the managerial approval workflow.

---

## Decisions
We have adopted the following architecture for the Inventory Adjustment Approval Engine:

### 1. Daily Header Grouping Strategy
Adjustments will not be saved as individual single-item documents. Instead, items are grouped into a daily session header (`inventory_adjustments`) unique to:
- `warehouse_id`
- `operator_id`
- `date` (Business date)
- `status = 'WAITING_APPROVAL'`

If an operator submits multiple variances on the same day, they are appended to this single daily header. A new header is only generated if no waiting header exists for that combo. This simplifies Berita Acara Stock Opname (BASO) reports and prevents document bloat.

### 2. Item-Level Approval & Derived Header Status
Managers review and approve/reject individual items (`inventory_adjustment_items`), never the header as a whole. This ensures that valid discrepancies can be resolved immediately, while disputed items are isolated. The header status is **derived** from the states of its child items:
- `WAITING_APPROVAL`: Header contains one or more items with status `WAITING`.
- `COMPLETED`: All child items have been processed (status `APPROVED` or `REJECTED`).
The header status is recalculated automatically using a `synchronizeStatus()` database trigger or helper and is never modified manually.

### 3. Final Rejection Policy
Rejection of a variance is final. An operator cannot edit or resubmit a rejected adjustment row. To correct it, the operator must return to `/opname`, scan the bin again, and log a new adjustment. This creates a clear, sequential audit trail of physical checks rather than allowing state modification of rejected records.

### 4. Immutable Historical Snapshots
To ensure historical reports remain readable even if catalog data is modified or users/warehouses are renamed, every adjustment item stores text snapshots at the time of submission:
- `item_name_snapshot`
- `erp_code_snapshot`
- `bin_code_snapshot`
- `unit_snapshot`
- `warehouse_name_snapshot`
- `operator_name_snapshot`

### 5. Multi-Warehouse & Operator Isolation
Strict warehouse boundaries are enforced at the database level:
- **Operators** only see their own adjustments inside the currently active warehouse context.
- **Managers** see all adjustments inside the active warehouse context.
- Context switching is handled globally; no local warehouse selectors are allowed on the adjustments dashboard.

### 6. Transactional Consistency
All approvals must be executed within database transactions (`DB::transaction()`), using pesimistic locking on bins (`lockForUpdate()`) to prevent race conditions during parallel stock updates.

### 7. Governance Sidebar
Stock Opname and Inventory Adjustments are moved from the operations context (OPS) into a dedicated **Governance (GOV)** sidebar group to reinforce their role as audit activities.

### 8. Workload KPIs & Age Column
- The Manager's dashboard shows today's workload: `Pending Items`, `Approved Today`, `Rejected Today`, and `Today's Sessions` (constant counters when switching tabs).
- An `Age` column displays elapsed time (e.g. 5 minutes, 2 hours, 1 day) to help managers prioritize old pending entries.

---

## Consequences

### Positive
- **Guaranteed Governance**: No stock changes are written without manager oversight.
- **Audit-Ready History**: Snapshot tables guarantee that historical reports are frozen in time and trace back to specific approved sessions.
- **Operator Efficiency**: Operators scan continuously without waiting for approvals or loading multi-page forms.
- **Clean Reports**: Session grouping reduces document noise, resulting in clean, readable reports.

### Negative
- **Temporary Inconsistencies**: The digital ledger deviates temporarily from physical bin counts while items await approval.
- **Storage Overhead**: Redundant snapshot text columns increase database row size slightly.

### Trade-offs
We prioritized floor throughput and reporting simplicity over immediate ledger synchronization.

---

## Future Roadmap

### Phase 1: Approval Engine (Current)
- Database schema migrations and master seeders.
- Opname Page scanner integration with validation.
- Interactive Manager Dashboard with KPIs, filters, age columns, approval transactions, and final rejection flows.

### Phase 2: Berita Acara Stock Opname (PDF)
- Setup PDF export routes and layouts.
- Generate grouped daily summaries with dual operator-manager signature blocks on every page.

### Phase 3: Analytics & Digital Signatures
- Manager analytics (average approval times, operator error rates).
- Digital signature verification and automated SLA alert mechanisms.
