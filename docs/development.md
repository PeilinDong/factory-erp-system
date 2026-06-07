# Development Guide

## Runtime

Use PHP 8.3 to match the prepared server environment as closely as possible. The project intentionally avoids Composer in the first foundation so it can run on shared hosting through FTP upload.

## Structure

```text
bootstrap/          Web bootstrap and route registration
bin/                CLI entrypoints
config/             Example and local config files
database/           Migration definitions
docs/               Product, development, testing, and deployment docs
public/             Apache document root for /erp/
src/                Application source code
storage/            Runtime cache and logs
tests/              Custom test runner and tests
```

## Local Configuration

Create local config files from examples:

```powershell
Copy-Item config/app.example.php config/app.php
Copy-Item config/database.example.php config/database.php
```

Local config files are ignored by Git because they may contain passwords.

## Local Server

Run:

```powershell
php -S 127.0.0.1:8080 -t public
```

Open:

```text
http://127.0.0.1:8080/login
```

When testing the `/erp/` deployment prefix locally, Apache is closer to production than PHP's built-in server. The front controller strips `/erp` from incoming paths when deployed under that prefix.

## CLI

The CLI is for deployment, operations, and development. It is not a business-user workflow.

```powershell
php bin/erpctl health
php bin/erpctl migrate --dry-run
php bin/erpctl create-admin --email=admin@example.com --password=ChangeThisPassword123 --dry-run
```

