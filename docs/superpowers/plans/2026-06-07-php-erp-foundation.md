# PHP ERP Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the first deployable PHP 8.3 + MariaDB ERP foundation for an Apache shared-hosting environment under `/erp/`.

**Architecture:** Use a small no-Composer PHP architecture with explicit autoloading, focused core classes, a front controller, a simple CLI, and migration files. The code must run locally with PHP CLI and on the prepared Apache/PHP/MariaDB host through FTP deployment.

**Tech Stack:** PHP 8.3, PDO, MariaDB 10.5-compatible SQL, Apache `.htaccess`, plain CSS/HTML, custom lightweight test runner.

---

### Task 1: Testing Foundation

**Files:**
- Create: `tests/TestCase.php`
- Create: `tests/run.php`
- Create: `tests/Unit/FoundationTest.php`

- [x] **Step 1: Write failing tests**

Define tests for config lookup, route dispatch, CLI health output, migration SQL, and login page rendering.

- [x] **Step 2: Run tests to verify they fail**

Run: `php tests/run.php`
Expected: FAIL because production classes do not exist.

- [x] **Step 3: Implement minimal code**

Create autoloader, config, router, controller, migration, and CLI classes.

- [x] **Step 4: Run tests to verify they pass**

Run: `php tests/run.php`
Expected: all tests pass.

### Task 2: Shared Hosting App Skeleton

**Files:**
- Create: `public/index.php`
- Create: `public/.htaccess`
- Create: `public/assets/app.css`
- Create: `config/app.example.php`
- Create: `config/database.example.php`
- Create: `src/Controller/AuthController.php`
- Create: `src/Controller/DashboardController.php`

- [x] **Step 1: Add web entrypoint and styles**

The front controller routes `/`, `/login`, and `/health`.

- [x] **Step 2: Verify syntax**

Run: `php -l public/index.php`
Expected: no syntax errors.

### Task 3: CLI and Migration Skeleton

**Files:**
- Create: `bin/erpctl`
- Create: `src/Cli/Application.php`
- Create: `src/Cli/HealthCommand.php`
- Create: `src/Cli/MigrateCommand.php`
- Create: `src/Cli/CreateAdminCommand.php`
- Create: `database/migrations/202606070001_create_foundation_tables.php`

- [x] **Step 1: Implement command dispatch**

Support `health`, `migrate --dry-run`, and `create-admin --email=... --password=... --dry-run`.

- [x] **Step 2: Verify CLI behavior**

Run: `php bin/erpctl health`
Expected: OK output without needing database credentials.

### Task 4: Documentation

**Files:**
- Create: `README.md`
- Create: `docs/development.md`
- Create: `docs/testing.md`
- Create: `docs/deployment.md`
- Modify: `.gitignore`

- [x] **Step 1: Document local development and testing**

Explain PHP requirement, test command, local server command, and shared-hosting deployment layout.

- [x] **Step 2: Protect local secrets**

Ignore local config files and storage runtime output while keeping examples in git.

### Task 5: Verification and Push

**Files:**
- All changed files.

- [x] **Step 1: Run full verification**

Run syntax checks, unit tests, CLI smoke tests, and git status.

- [x] **Step 2: Commit and push**

Commit implementation and push to `origin/main`.

