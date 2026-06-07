# Contributing

Thank you for your interest in Factory ERP System.

This project is early alpha. Contributions should keep the codebase small, understandable, and useful for small and medium manufacturing companies.

## Ground Rules

- Do not describe the project as production-ready.
- Do not add claims about users, downloads, popularity, or adoption unless there is verifiable public evidence.
- Keep the user interface focused on Chinese factory users.
- Keep business workflows understandable to non-developers.
- Prefer small pull requests with a clear purpose.
- Add or update tests for behavior changes.
- Do not commit secrets, server credentials, database passwords, FTP credentials, or private customer data.
- Keep local config files out of Git.

## Development Setup

Copy local config examples:

```powershell
Copy-Item config/app.example.php config/app.php
Copy-Item config/database.example.php config/database.php
```

Run tests:

```powershell
php tests/run.php
```

Run a migration dry run:

```powershell
php bin/erpctl migrate --dry-run
```

Run a local server:

```powershell
php -S 127.0.0.1:8080 -t public
```

## Code Style

- Use `declare(strict_types=1);` in PHP files.
- Keep classes focused and easy to test.
- Follow the existing repository structure.
- Prefer explicit validation and clear error handling.
- Avoid adding dependencies unless they solve a real project need.

## Testing

The project uses a custom PHP test runner:

```powershell
php tests/run.php
```

Before opening a pull request, run:

```powershell
php tests/run.php
php bin/erpctl migrate --dry-run
```

For PHP syntax checks:

```powershell
Get-ChildItem -Recurse -Include *.php | ForEach-Object { php -l $_.FullName }
```

## Issues

Good issues include:

- A clear description of the manufacturing workflow or technical problem.
- The expected behavior.
- The current behavior.
- Screenshots or example data when useful.
- Whether the issue affects material master, warehouse, inventory, BOM, purchasing, work orders, traceability, or cost visibility.

## Pull Requests

Good pull requests include:

- A short explanation of the problem.
- A focused implementation.
- Tests or a clear explanation of what was manually verified.
- Documentation updates when behavior or setup changes.

For larger modules, open an issue first so the workflow can be discussed before implementation.
