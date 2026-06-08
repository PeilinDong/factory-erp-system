# Release Notes

## v0.1.0-alpha

Initial early-alpha release of Factory ERP System.

This release is intended for open development, testing, and early feedback. It is not production-ready.

### Included

- Plain PHP 8.3 application foundation with a lightweight autoloader and router.
- MariaDB-compatible migration definitions.
- Administrator login, session handling, and CSRF protection.
- Protected dashboard with inventory-backed metrics.
- Material and warehouse master data pages with search, edit, and enable/disable actions.
- BOM management foundation with requirement calculation.
- Purchase order foundation with receipt into inventory.
- Production work order foundation with material issue and finished-goods receipt.
- Inventory transaction entry for inbound, outbound, and adjustment records.
- Stock balance page by material and warehouse.
- Batch traceability foundation.
- Basic material shortage analysis for planned work orders.
- User management foundation with role assignment, enable/disable actions, and key operation permission checks.
- CLI commands for health checks, migrations, and administrator creation.
- Shared-hosting deployment package builder.
- Custom PHP test runner and GitHub Actions workflow for tests.

### Known Gaps

- Not production-ready.
- No installer or upgrade wizard.
- No Excel import/export.
- No purchasing suggestion generation from shortage analysis yet.
- No partial purchase receipt, purchase return, work order return, supplement issue, or partial completion.
- No cost reporting yet.
- No operation audit log yet.
- No data-scope permissions by organization, warehouse, or document type.
- Limited reporting, printing, and export support.

### Verification

- Local test command: `php tests/run.php`
- CI workflow: `.github/workflows/php-tests.yml`
