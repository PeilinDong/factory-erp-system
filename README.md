# Factory ERP System

Factory ERP System is an early-alpha open-source ERP project for small and medium manufacturing companies, with an initial focus on Chinese factory workflows.

The project aims to provide a lightweight PHP and MariaDB foundation for material management, warehouse management, BOM, inventory transactions, purchasing suggestions, work orders, traceability, and basic cost visibility.

This repository is not production-ready. It is an early foundation intended for open development, testing, and feedback.

## Why This Project Exists

Many small and medium factories still rely on spreadsheets, manual handoffs, and disconnected tools for daily production and material control. Full ERP products can be expensive, complex, or difficult to adapt to factory-specific workflows.

Factory ERP System is intended to grow into a practical, inspectable, and self-hostable ERP foundation that smaller manufacturers can understand, modify, and extend.

## Target Users

- Small and medium manufacturing companies.
- Factory owners and operations managers who need better material visibility.
- Production planners and warehouse staff who need clearer inventory movement records.
- Developers and implementation partners who want a lightweight ERP base for custom factory workflows.
- Open-source contributors interested in manufacturing software, PHP, MariaDB, and business systems.

## Current Status

Early alpha.

Implemented so far:

- PHP 8.3 application foundation without Composer dependency.
- Lightweight autoloader and router.
- Web front controller at `public/index.php`.
- Database-backed administrator login.
- Session protection and CSRF checks.
- Protected dashboard.
- Material master page.
- Warehouse master page.
- Inventory transaction page for inbound, outbound, and adjustment records.
- MariaDB-compatible foundation migrations.
- CLI entrypoint: `bin/erpctl`.
- CLI commands: `health`, `migrate`, and `create-admin`.
- Shared-hosting deployment package builder.
- Custom PHP test runner.

Not implemented yet:

- BOM management.
- Inventory inbound, outbound, and adjustment transactions.
- Purchasing suggestions.
- Production work orders.
- Traceability reports.
- Cost reports.
- Permission management beyond the initial administrator foundation.
- Production hardening, installer flow, and upgrade tooling.

## Technology

- PHP 8.3
- MariaDB 10.5 compatible SQL
- Apache 2.4
- Plain PHP architecture
- Custom test runner
- Shared-hosting friendly deployment

The project intentionally starts with a small dependency footprint so it can run in constrained hosting environments while the domain model is still being shaped.

## Installation

Copy example config files:

```powershell
Copy-Item config/app.example.php config/app.php
Copy-Item config/database.example.php config/database.php
```

Edit:

```text
config/app.php
config/database.php
```

Run migration after database configuration is available:

```powershell
php bin/erpctl migrate
```

Create an administrator:

```powershell
php bin/erpctl create-admin --email=admin@example.com --password=ChangeThisPassword123
```

Run a local server:

```powershell
php -S 127.0.0.1:8080 -t public
```

Open:

```text
http://127.0.0.1:8080/login
```

## Development

Run tests:

```powershell
php tests/run.php
```

Run a CLI health check:

```powershell
php bin/erpctl health
```

Run a migration dry run:

```powershell
php bin/erpctl migrate --dry-run
```

Build a shared-hosting deployment package:

```powershell
php scripts/build_shared_host.php
```

More details:

- [Development Guide](docs/development.md)
- [Testing Guide](docs/testing.md)
- [Deployment Guide](docs/deployment.md)

## Roadmap

The roadmap is intentionally phased because ERP systems become risky when too many business modules are built before the data foundation is stable.

See [ROADMAP.md](ROADMAP.md).

Near-term priorities:

1. Stock balance list by material and warehouse.
2. Material and warehouse search, edit, and enable/disable actions.
3. Excel import/export for master data.
4. BOM foundation.
5. Work order foundation.

## Contributing

Contributions are welcome, especially in the areas of manufacturing workflows, PHP/MariaDB implementation, tests, documentation, and Chinese ERP localization.

Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening issues or pull requests.

## OpenAI Codex For Open Source Application

This repository includes an application note describing why the project matters and how Codex can help accelerate open-source development:

- [docs/openai-codex-oss-application.md](docs/openai-codex-oss-application.md)

## License

MIT. See [LICENSE](LICENSE).
