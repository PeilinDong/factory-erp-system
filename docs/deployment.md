# Deployment Guide

## Target Environment

The prepared target environment is:

- Apache 2.4
- PHP 8.3
- MariaDB 10.5
- Public URL prefix: `/erp/`
- Upload method: FTP

Do not commit FTP, database, or administrator passwords. Keep them in local-only notes or local config files.

## Files To Upload

Upload the project contents except local-only files ignored by Git. The server web path should expose the contents of `public/` under:

```text
https://www.goenn.online/erp/
```

If the hosting panel maps `/erp/` to a directory, place files so that `public/index.php` is the web entrypoint. If the host cannot set `public/` as document root, copy the contents of `public/` into the web-visible `/erp/` directory and keep `src/`, `bootstrap/`, `config/`, `database/`, and `storage/` outside the public directory when the host allows it.

## Server Configuration

Create these files from examples and fill in real values on the server:

```text
config/app.php
config/database.php
```

Use `utf8mb4` for MariaDB.

## Smoke Tests After Upload

Open:

```text
https://www.goenn.online/erp/login
https://www.goenn.online/erp/health
```

Expected:

- `/login` shows the Chinese ERP login page.
- `/health` returns `Factory ERP OK`.

## Operational CLI

On the server, use PHP 8.3 CLI when available:

```bash
/usr/bin/php8.3 bin/erpctl health
/usr/bin/php8.3 bin/erpctl migrate --dry-run
```

Database-backed migration execution will be added after the first schema connection layer is implemented.

