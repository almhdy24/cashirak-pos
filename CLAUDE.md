# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Running the Application

**Windows:**
```bat
start.bat
```
This finds a free port (8000–8100), starts PHP's built-in server (`php -S 127.0.0.1:[PORT] -t public/`), and opens the browser automatically.

**Linux/Termux/Mac:**
```bash
php -S 127.0.0.1:8000 -t public/
```

No build step, no composer install — the app runs directly. There are no automated tests.

## First-Run Flow

1. `bootstrap.php` checks for `storage/installed.lock` → redirects to `/install.php` if missing
2. Installer creates SQLite DB, seeds settings, activates license (requires internet + license key)
3. On success, writes `storage/installed.lock`
4. Subsequent boots: bootstrap validates cached license (`storage/.license_data`) → re-verifies with remote API if cache is stale (>24h)

## Architecture

No framework, no composer dependencies. Custom SPL autoloader maps namespaces to directories.

**Request lifecycle:**
```
public/*.php (controller/page file)
  → include bootstrap.php
    → License::init(), session start, autoloader
  → AuthMiddleware::handle($permission)
  → Models / Repositories / Services
  → Render HTML (inline, no template engine)
```

**Layer responsibilities:**
- `app/Core/` — infrastructure: `DB` (PDO singleton → SQLite), `Session`, `Auth`, `Security`, `Settings`, `License`, `Installer`
- `app/Models/` — active record pattern (static methods over `DB`)
- `app/Repositories/` — multi-step DB operations with explicit transactions
- `app/Services/` — business logic (order processing, shift management)
- `app/Middleware/` — auth + CSRF validation
- `public/*.php` — page/controller files; there is no router
- `views/partials/` — shared `header.php` / `footer.php` included by pages
- `storage/cashirak.sqlite` — the only database; all state lives here

## Key Patterns

**Auth:**
```php
AuthMiddleware::handle('process_order'); // enforces login + permission
Auth::user();                            // ['id', 'username', 'role', 'permissions']
// role=admin bypasses permission checks; cashier checks JSON permissions array
```

**CSRF:** Every POST must include a token validated by `Security::validateCSRFToken()`.

**Settings:** Stored in `settings` DB table. Read with `getSetting('key', 'default')` or `Settings::get()`; written with `Settings::set()`.

**Transactions:** Use `DB::beginTransaction()` / `commit()` / `rollBack()` for any multi-step writes (see `OrderRepository`).

**Order creation flow:**
```
index.php (JS cart) → AJAX POST → order.php → OrderService::processOrder()
  → OrderRepository::createWithItems() [transaction]
  → print.php (browser print dialog)
```

## Database

SQLite only (`storage/cashirak.sqlite`). Main tables: `users`, `items`, `categories`, `orders`, `order_items`, `shifts`, `settings`, `audit_logs`. Schema created by `Installer::createTables()`.

## License System

`app/Core/License.php` — one-time online activation via ElmahdiPay SDK (`https://api.almhdy24.com`), then purely offline forever. On every boot: (1) RSA-verify the signed `license_key` token against `certs/license_public.pem`, (2) check device UUID matches `storage/.device_id`, (3) check `time() >= last_seen_time` (blocks clock rollback), (4) write new `last_seen_time`. No re-verification, no grace period, no internet after activation.

## Frontend

Bootstrap 5 RTL (`bootstrap.rtl.min.css`) — Arabic is the primary language. Vanilla JS only (no jQuery). Keyboard shortcuts on the main POS screen: F2 = search, Enter = checkout, Esc = clear.

## File Paths Reference

| What | Where |
|------|-------|
| DB connection | `app/Core/DB.php` |
| Bootstrap / autoloader | `bootstrap.php` |
| Installer | `app/Core/Installer.php` |
| License logic | `app/Core/License.php` |
| Main POS page | `public/index.php` |
| Order API endpoint | `public/order.php` |
| Admin panel | `public/admin.php` |
| Shared header/footer | `views/partials/` |
| SQLite DB file | `storage/cashirak.sqlite` |
| License cache | `storage/.license_data` |
