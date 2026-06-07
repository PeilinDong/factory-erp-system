# Factory ERP System

Factory ERP System is an open-source ERP project for Chinese small and medium manufacturing companies. The first implementation phase focuses on production material control: materials, BOM, inventory, MRP readiness checks, purchasing suggestions, work orders, issue/return material flows, traceability, and basic cost visibility.

The current codebase is the first PHP foundation for the prepared shared-hosting environment:

- PHP 8.3
- Apache 2.4
- MariaDB 10.5
- Deployable under `/erp/`
- No Composer dependency required for the first foundation

## Current Features

- Lightweight PSR-4-style autoloader
- Web front controller at `public/index.php`
- Routes for dashboard, login, and health check
- Chinese login page for the ERP positioning
- Database-backed administrator login
- Protected dashboard routes with session login
- CSRF protection for login and logout forms
- Configurable deployment base path
- CLI entrypoint: `bin/erpctl`
- CLI commands: `health`, `migrate`, `create-admin`
- MariaDB-compatible foundation migration definitions
- Custom PHP test runner

## Quick Start

Copy example config files before connecting a real database:

```powershell
Copy-Item config/app.example.php config/app.php
Copy-Item config/database.example.php config/database.php
```

Run tests:

```powershell
php tests/run.php
```

Run CLI health check:

```powershell
php bin/erpctl health
```

After database configuration is available, run migrations and create an administrator:

```powershell
php bin/erpctl migrate
php bin/erpctl create-admin --email=admin@example.com --password=ChangeThisPassword123
```

Run local web server:

```powershell
php -S 127.0.0.1:8080 -t public
```

Then open:

```text
http://127.0.0.1:8080/login
```

## Documentation

- [Development](docs/development.md)
- [Testing](docs/testing.md)
- [Deployment](docs/deployment.md)
- [MVP Design](docs/superpowers/specs/2026-06-07-china-manufacturing-erp-mvp-design.md)
