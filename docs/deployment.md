# Deployment Guide

## Target Environment

The prepared target environment is:

- Apache 2.4
- PHP 8.3
- MariaDB 10.5
- Public URL prefix: `/erp/`
- Upload method: FTP

Do not commit FTP, database, or administrator passwords. Keep them in local-only notes or local config files.

## Build Deploy Package

Create a shared-hosting package:

```powershell
php scripts/build_shared_host.php
```

The package is written to:

```text
build/shared-host/
```

## Files To Upload

Upload the contents of `build/shared-host/` to the FTP directory that maps to:

```text
https://www.goenn.online/erp/
```

The deploy package keeps public files in the web root and moves application code into `_app/`. `_app/.htaccess` blocks direct HTTP access to internal code on Apache.

## Server Configuration

Create these files from examples and fill in real values on the server:

```text
config/app.php
config/database.php
```

Use `utf8mb4` for MariaDB.

Set `base_path` in `config/app.php` to the public URL prefix. For the current server this is:

```php
'base_path' => '/erp',
```

## Smoke Tests After Upload

Open:

```text
https://www.goenn.online/erp/login
https://www.goenn.online/erp/health
```

Expected:

- `/login` shows the Chinese ERP login page.
- `/health` returns `Factory ERP 正常`.

## Operational CLI

On the server, use PHP 8.3 CLI when available:

```bash
/usr/bin/php8.3 bin/erpctl health
/usr/bin/php8.3 bin/erpctl migrate --dry-run
```

Database-backed migration and administrator creation are now supported when `config/database.php` exists:

```bash
/usr/bin/php8.3 bin/erpctl migrate
/usr/bin/php8.3 bin/erpctl create-admin --email=admin@example.com --password=ChangeThisPassword123
```
