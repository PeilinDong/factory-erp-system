# Roadmap

Factory ERP System is an early-alpha project. The roadmap is expected to change as the domain model, user feedback, and implementation experience improve.

The project will avoid claiming production readiness until core ERP workflows, tests, security review, deployment guidance, and upgrade paths are more mature.

## Phase 0 - Foundation

Status: in progress.

Goals:

- Plain PHP application foundation.
- MariaDB connection and migrations.
- Administrator login.
- Protected dashboard.
- Shared-hosting deployment package.
- CLI health, migration, and administrator commands.
- Basic test runner and verification workflow.

Current implemented modules:

- Material master.
- Warehouse master.
- Project-based BOM management foundation.
- Purchase order foundation.
- Production work order foundation.
- Inventory transaction entry.
- Stock balance page.
- Batch traceability page.
- Basic material shortage analysis for planned work orders.
- User management foundation.
- Dashboard inventory metrics.

## Phase 1 - Inventory Foundation

Goals:

- Inventory inbound transactions.
- Inventory outbound transactions.
- Inventory adjustments.
- Stock balance calculation by material and warehouse.
- Basic inventory transaction list.
- Dashboard metrics based on real inventory data.

Current status:

- Inbound, outbound, and adjustment transaction entry is implemented.
- Basic transaction listing is implemented.
- Service-level stock balance calculation is implemented.
- A dedicated stock balance page is implemented.
- Dashboard inventory metrics are implemented.

Why this phase matters:

Inventory is the foundation for BOM, purchasing, work orders, and traceability. The system should not build complex planning features before stock movement is reliable.

## Phase 2 - Master Data Completion

Goals:

- Material search and filters.
- Material edit and enable/disable actions.
- Warehouse edit and enable/disable actions.
- Excel import/export for material and warehouse data.
- Basic audit log for master-data changes.

Why this phase matters:

Many small factories start ERP adoption from spreadsheet data. Import/export and clear master-data maintenance are essential for adoption.

## Phase 3 - BOM And Planning

Goals:

- Multi-level BOM foundation.
- BOM versioning.
- BOM expansion.
- Material requirement calculation.
- Shortage checks.
- Purchasing suggestion draft generation.

Current status:

- Single-level project BOM creation and requirement calculation are implemented.
- Basic shortage analysis for planned work orders is implemented.
- Multi-level expansion, effective-date control, and purchasing suggestion generation are not implemented yet.

Why this phase matters:

BOM and requirement calculation turn inventory records into production planning value.

## Phase 4 - Purchasing And Work Orders

Goals:

- Purchasing request or suggestion workflow.
- Purchase order foundation.
- Work order creation.
- Work order material issue and return.
- Finished-goods receipt.
- Work order status tracking.

Current status:

- Purchase order creation and full receipt into inventory are implemented.
- Work order creation, full material issue, and full finished-goods receipt are implemented.
- Partial receipt, purchase return, work order return, supplement issue, and richer status control are not implemented yet.

Why this phase matters:

This phase connects planning to daily factory execution.

## Phase 5 - Traceability And Cost Visibility

Goals:

- Material movement traceability.
- Batch or lot tracking foundation.
- Work order material cost summary.
- Basic cost variance visibility.
- Printable and exportable reports.

Current status:

- Batch number recording and basic batch traceability are implemented.
- Cost summaries and printable/exportable reports are not implemented yet.

Why this phase matters:

Traceability and cost visibility are major reasons factories move beyond spreadsheets.

## Phase 6 - Hardening

Goals:

- Role and permission management.
- Installer and upgrade flow.
- Backup and restore guidance.
- Security review.
- More complete automated test coverage.
- UI accessibility review.
- Production deployment checklist.

Current status:

- Basic user management with administrator, general manager, supervisor, planner, warehouse, and purchasing roles is implemented, including enable/disable actions and key operation permission checks.
- Key operation permission checks are implemented for user management, purchasing, inventory, and work order actions.
- Operation audit logs and data-scope controls are not implemented yet.

Why this phase matters:

The project should remain honest about readiness. Production use requires stronger operational and security guarantees than the current early-alpha foundation provides.
