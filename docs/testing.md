# Testing Guide

## Test Runner

The project uses a small custom PHP test runner:

```powershell
php tests/run.php
```

The runner loads `tests/Unit/*Test.php`, executes methods beginning with `test`, and exits with non-zero status when any test fails.

## Current Coverage

Current tests verify:

1. Nested config lookup with defaults.
2. Router dispatch and 404 behavior.
3. Router behavior after output has started.
4. CLI health command output.
5. Migration dry-run table listing.
6. Login page Chinese ERP positioning.
7. Password-hash based authentication.
8. Rejection of missing, inactive, or invalid users.
9. Shared-host deployment package layout.
10. Configured base-path URL generation.
11. CSRF-protected login and logout.
12. Guest redirect from protected dashboard pages.
13. Dashboard links only expose implemented modules and avoid dead links.
14. Material master creation, validation, and protected page rendering.
15. Warehouse master creation, validation, and protected page rendering.
16. Supplier master creation, search, enable/disable actions, and purchase order supplier selection.
17. Customer master creation, search, enable/disable actions, and sales order customer selection.
18. Sales order foundation with customer, finished-goods material, quantity, due date, draft status, and conversion to production work order drafts.
19. Inventory transactions, quantity validation, protected page rendering, and stock balance calculation.
20. Stock balance page rendering and dashboard inventory metrics.
21. Project BOM creation, search, enable/disable actions, inactive-BOM protection for new work orders, purchase order approval/cancel/close, production work order, material issue, full and partial purchase receipt, purchase return, finished-goods receipt, and duplicate operation protection.
22. Batch traceability and shared primary navigation coverage.
23. Basic material shortage analysis for planned work orders, purchasing suggestion generation, and conversion to purchase order drafts.
24. User management foundation with administrator, general manager, supervisor, planner, warehouse, and purchasing roles; enable/disable actions; six-character development password coverage for admin creation; and key operation permission checks.

## Verification Commands

Run these before every commit:

```powershell
php tests/run.php
php -l public/index.php
php -l bin/erpctl
php bin/erpctl health
php bin/erpctl migrate --dry-run
```

For a broad syntax check on Windows PowerShell:

```powershell
Get-ChildItem -Recurse -Include *.php | ForEach-Object { php -l $_.FullName }
```
