<div align="center">

<img src="installer_assets/icon_source.svg" width="96" alt="Cashirak POS icon">

# Cashirak POS · كاشيراك

**Offline-first point of sale for restaurants, cafés, groceries and kiosks.**
Arabic-first (RTL), runs on any old Windows PC, no internet needed after activation.

[![Version](https://img.shields.io/badge/version-1.0.0-2563eb)](#)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php&logoColor=white)](#requirements)
[![SQLite](https://img.shields.io/badge/database-SQLite-003b57?logo=sqlite&logoColor=white)](#how-it-works)
[![Windows](https://img.shields.io/badge/Windows-10%2B-0078d4?logo=windows&logoColor=white)](#installation)
[![Offline](https://img.shields.io/badge/works-offline-16a34a)](#offline-by-design)
[![RTL](https://img.shields.io/badge/UI-Arabic%20RTL-f59e0b)](#)

**English** · [العربية](README.ar.md) · [Website](https://almhdy24.com)

<img src="docs/showcase/webp/hero.webp" alt="Cashirak POS — cashier screen, admin dashboard and printed receipt">

</div>

---

## Table of contents

- [What is Cashirak POS?](#what-is-cashirak-pos)
- [Screenshots](#screenshots)
- [Features](#features)
- [How it works](#how-it-works)
- [Requirements](#requirements)
- [Installation](#installation)
- [First run: setup wizard & license](#first-run-setup-wizard--license)
- [Daily workflow](#daily-workflow)
- [Roles & permissions](#roles--permissions)
- [Keyboard shortcuts & barcode scanners](#keyboard-shortcuts--barcode-scanners)
- [Where your data lives & backups](#where-your-data-lives--backups)
- [Security](#security)
- [Project structure](#project-structure)
- [Screenshots & marketing images (for the website)](#screenshots--marketing-images-for-the-website)
- [FAQ](#faq)
- [Support](#support)

---

## What is Cashirak POS?

Cashirak POS (كاشيراك) is a complete cashier system built for small businesses in Sudan and the region:
cafés, restaurants, juice bars, groceries and kiosks. One PC (or tablet) runs the whole shop:

- the **cashier** taps items, picks a payment method (cash, Bankak, MyCashi, bank transfer…) and prints a receipt;
- the **owner/admin** manages the menu, stock, expenses, users and settings, closes the shift with a
  cash-drawer reconciliation and reads daily / weekly reports.

Everything is stored locally in a single SQLite file. There is no cloud, no monthly fee and no
dependency on the network: power cuts and internet outages don't stop the sale.

---

## Screenshots

> All screenshots are generated automatically from the real app with demo data — see
> [Screenshots & marketing images](#screenshots--marketing-images-for-the-website).
> Click any image for the full-resolution PNG.

### Selling

| Cashier screen | Barcode scanning |
|---|---|
| [![POS](docs/showcase/webp/pos-cart.webp)](docs/showcase/pos-cart.png) | [![Barcode](docs/showcase/webp/pos-barcode.webp)](docs/showcase/pos-barcode.png) |
| **Categories & instant search** | **Printed receipt** |
| [![Categories](docs/showcase/webp/pos-category.webp)](docs/showcase/pos-category.png) | [![Receipt](docs/showcase/webp/receipt.webp)](docs/showcase/receipt.png) |
| **Order history (reprint / cancel)** | **Returns** |
| [![History](docs/showcase/webp/history.webp)](docs/showcase/history.png) | [![Returns](docs/showcase/webp/returns.webp)](docs/showcase/returns.png) |

<p align="center">
  <a href="docs/showcase/devices.png"><img src="docs/showcase/webp/devices.webp" width="85%" alt="Cashirak POS on a tablet and a phone"></a>
</p>

### Managing

| Admin dashboard | Sales reports |
|---|---|
| [![Dashboard](docs/showcase/webp/admin-dashboard.webp)](docs/showcase/admin-dashboard.png) | [![Reports](docs/showcase/webp/admin-reports.webp)](docs/showcase/admin-reports.png) |
| **Shift close & cash reconciliation** | **Shift history** |
| [![Shift close](docs/showcase/webp/shift-close.webp)](docs/showcase/shift-close.png) | [![Shift history](docs/showcase/webp/admin-shift-history.webp)](docs/showcase/admin-shift-history.png) |
| **Detailed shift report** | **Shift expenses** |
| [![Shift details](docs/showcase/webp/admin-shift-details.webp)](docs/showcase/admin-shift-details.png) | [![Expenses](docs/showcase/webp/admin-expenses.webp)](docs/showcase/admin-expenses.png) |
| **Items, cost, barcode & stock** | **Payment methods** |
| [![Items](docs/showcase/webp/admin-add-item.webp)](docs/showcase/admin-add-item.png) | [![Payment methods](docs/showcase/webp/admin-payment-methods.webp)](docs/showcase/admin-payment-methods.png) |
| **Users & permissions** | **Shop & receipt settings** |
| [![Users](docs/showcase/webp/admin-users.webp)](docs/showcase/admin-users.png) | [![Settings](docs/showcase/webp/admin-settings.webp)](docs/showcase/admin-settings.png) |
| **Backup & restore** | **Setup wizard** |
| [![Backup](docs/showcase/webp/admin-backup.webp)](docs/showcase/admin-backup.png) | [![Setup](docs/showcase/webp/setup-1-shop.webp)](docs/showcase/setup-1-shop.png) |

Raw, unframed screenshots of every screen are in [`docs/screenshots/`](docs/screenshots).

---

## Features

### 🛒 Selling (cashier screen)
- Large, touch-friendly item buttons grouped by **category tabs**, plus **instant search** by name or barcode.
- **Best-sellers bar** (★) — the top 5 items of the current shift, one tap away.
- Cart with **+ / −** quantity controls, live total and item counter.
- **Payment method per order**: كاش (cash), بنكك (Bankak), ماي كاشي (MyCashi), تحويل بنكي (bank transfer) — fully configurable.
- **One-click “sell & print”**: the order is saved and the receipt opens in the print dialog.
- **Barcode scanner support**: scanning a code adds the item instantly; unknown codes show a warning.
- **Out-of-stock items** are greyed out and can't be sold.
- **Offline-resilient checkout**: orders are queued in the browser and retried automatically
  (a “pending orders” badge appears), each with an **idempotency key** so a retry never creates a duplicate.
- **Server-side price check**: totals are recalculated from the database — prices can't be tampered with from the browser.
- Fullscreen mode, help dialog and **keyboard shortcuts** (F2 / Enter / Esc).

### 🧾 Receipts, history & returns
- Printable receipt with shop name, date/time, receipt number, payment method, lines, total and a custom footer.
- **Shift order history** with reprint; admins can **cancel** an order (removed from the totals and recorded in the audit log).
- **Returns**: look up an order, choose which items/quantities to return, the reason and the refund method.
  Each order can be returned once; quantities can't exceed what was sold.

### 💵 Shifts & cash drawer
- A shift opens automatically with the first login/sale; every order belongs to a shift.
- **Opening cash** (the float in the drawer) is entered on the admin dashboard.
- **Shift expenses** (ice, bread, gas…) recorded during the shift.
- **Shift close with reconciliation**: payment-method breakdown, best sellers, expected cash
  (`opening cash + cash sales − expenses`) vs. **counted cash**, difference and a note.
- **Shift history** and a **detailed report per shift** (duration, totals, average ticket, payment breakdown, best sellers).

### 📦 Menu & inventory
- Items with **price, cost price, barcode, category and optional stock**.
- Stock is deducted automatically on every sale; **low-stock and out-of-stock alerts** on the reports page.
- Categories and payment methods are managed from the admin menu.

### 📊 Reports
- **Today**: sales, number of transactions, average sale, breakdown by payment method.
- **Last 7 days**: sales, transactions and returns per day.
- **Custom date range** filter.
- **Stock alerts**.

### 👥 Users, settings & maintenance
- Multiple users, **roles (admin / cashier) and permissions** (`process_order`, `manage_items`).
- Shop settings: **shop name, currency, receipt footer, date format**.
- **Backup** (download the database) and **restore** (with automatic safety copy of the current database).
- **Audit log** of order creation and cancellation.

### 🖥️ Platform
- Arabic **RTL** interface (Bootstrap 5 RTL), all assets bundled locally — **no CDN, no internet**.
- Runs on **Windows 10+** via a one-click installer with a bundled PHP runtime, or on **Linux / macOS / Termux** with PHP.
- Works on desktops, laptops, **tablets and phones** (responsive layout).

---

## How it works

### Architecture

Cashirak POS is a plain PHP application — no framework, no Composer, no build step. PHP's built-in web
server serves the `public/` folder on `127.0.0.1` and the browser is the UI.

```mermaid
flowchart LR
    subgraph PC["Shop PC (offline)"]
        B["Browser<br/>(cashier / admin UI)"] -- "HTTP 127.0.0.1:8000" --> S["PHP built-in server<br/>public/*.php"]
        S --> BS["bootstrap.php<br/>paths · autoloader · session · license check"]
        BS --> MW["AuthMiddleware<br/>login · permission · CSRF"]
        MW --> L["Models / Repositories / Services"]
        L --> DB[("SQLite<br/>database/cashirak.sqlite")]
        P["Printer"] -. "browser print" .- B
    end
    LS["License server<br/>api.almhdy24.com"] -. "one-time activation only" .- S
```

| Layer | Folder | Responsibility |
|---|---|---|
| Pages / controllers | `public/*.php`, `public/admin/*.php` | One file per screen (no router). Handles the request and renders HTML. |
| Bootstrap | `bootstrap.php`, `app/Core/paths.php` | Paths, version, error log, install check, autoloader, session, license gate. |
| Core | `app/Core/` | `DB` (PDO → SQLite), `Session`, `Auth`, `Security` (CSRF, hashing), `Settings`, `License`, `Installer`. |
| Models | `app/Models/` | Static active-record style access to `items`, `orders`, `shifts`, `users`, `categories`… |
| Repositories / Services | `app/Repositories/`, `app/Services/` | Multi-step writes in transactions (create order + lines + stock + audit), cancel order, close shift. |
| Middleware | `app/Middleware/AuthMiddleware.php` | Requires login + permission, validates CSRF on every POST. |
| Views | `views/partials/` | Shared header (navbar, styles) and footer (confirm modal, scripts). |
| Licensing SDK | `app/ElmahdiPay/` | Activation / purchase API client and RSA token verifier. |

### Selling an order

```mermaid
sequenceDiagram
    actor C as Cashier
    participant UI as index.php (browser)
    participant Q as localStorage queue
    participant API as order.php
    participant DB as SQLite
    C->>UI: tap items, choose payment, "إتمام البيع وطباعة"
    UI->>Q: save order + idempotency key
    Q->>API: POST JSON (CSRF header)
    API->>DB: key already used? → return existing order
    API->>DB: BEGIN · recalc total from item prices · insert order + lines · deduct stock · audit log · COMMIT
    API-->>UI: { status: success, order_id }
    UI->>Q: remove from queue
    UI->>C: toast + open print.php?id=… (receipt)
    Note over Q,API: If the request fails, the order stays queued<br/>("pending" badge) and is retried later.
```

### Shift lifecycle

```mermaid
stateDiagram-v2
    [*] --> Open: first login or sale
    Open --> Open: orders · expenses · returns · cancellations
    Open --> Closed: admin closes shift<br/>counts cash → difference + note
    Closed --> [*]: visible in shift history & reports
```

Expected cash at close = **opening cash + cash sales − shift expenses**. The admin types the counted
amount; the difference (shortage / surplus) is saved with the shift.

### Data model

```mermaid
erDiagram
    users ||--o{ orders : "cashier_id"
    shifts ||--o{ orders : "shift_id"
    orders ||--|{ order_items : "order_id"
    categories ||--o{ items : "category_id"
    shifts ||--o{ expenses : "shift_id"
    orders ||--o| returns : "order_id"
    returns ||--|{ return_items : "return_id"
    users {
        int id
        text username
        text role
        text permissions
    }
    items {
        int id
        text name
        text barcode
        real price
        real cost_price
        int stock
        int min_stock
    }
    orders {
        int id
        real total
        text payment_method
        text status
        text idempotency_key
    }
    shifts {
        int id
        text start_time
        text end_time
        real opening_cash
        real actual_cash
        text close_note
    }
```

Other tables: `payment_methods`, `settings` (key/value), `audit_logs`. The schema is created by
`Core\Installer` and kept up to date by idempotent migrations in `app/helpers.php` on every request,
so upgrading is just replacing the program files.

### Offline by design

- All CSS, JS, icons and fonts are bundled in `public/assets/` — nothing is loaded from the internet.
- The only network call the app ever makes is the **one-time license activation** (or buying a license).
  After that the license is verified locally with an RSA public key.
- Checkout survives a hiccup in the local server: orders wait in a browser queue and are sent when possible.

---

## Requirements

| | Windows (installer) | Linux / macOS / Termux |
|---|---|---|
| OS | Windows 10 or newer, 64-bit | any |
| PHP | bundled — nothing to install | PHP **8.0+** with `pdo_sqlite`, `openssl`, `curl` |
| Browser | any modern browser (Chrome, Edge, Firefox) | same |
| Printer | any printer installed in the OS (thermal or A4) | same |
| Internet | only once, to activate the license | same |

---

## Installation

### Option 1 — Windows installer (recommended)

1. Run **`CashirakPOS-Setup.exe`** and follow the wizard (optionally create a desktop shortcut).
2. Start **Cashirak POS** from the Start menu / desktop. A silent launcher starts the local server on the
   first free port between 8000–8100 and opens the browser.
3. Complete the [setup wizard](#first-run-setup-wizard--license).

Program files go to `C:\Program Files\Cashirak POS`; your data goes to `C:\ProgramData\Cashirak POS`,
so updates and reinstalls never touch your sales.

> Building the installer yourself: put a 64-bit PHP 8 (NTS zip) in `php\`, then run `iscc CashirakPOS.iss`
> with Inno Setup 6. Output: `Output\CashirakPOS-Setup.exe`.

### Option 2 — Portable Windows folder

1. Copy the project folder to the PC and extract PHP 8 (Non-Thread-Safe zip from windows.php.net) into `php\`.
2. Double-click **`start.bat`**. It creates the data folders, picks a free port and opens the browser.

### Option 3 — Linux / macOS / Termux

```bash
git clone https://github.com/almhdy24/cashirak-pos.git
cd cashirak-pos
php -S 127.0.0.1:8000 -t public
```

Open <http://127.0.0.1:8000> — you'll be redirected to the setup wizard. To keep data elsewhere, set
`CASHIRAK_DATA_PATH=/path/to/data` before starting PHP.

---

## First run: setup wizard & license

| Step | What happens |
|---|---|
| **1 · Shop** | Requirement check (PHP, SQLite, OpenSSL, cURL, writable storage) + shop name, currency, receipt footer, date format. |
| **2 · Admin** | Choose the admin username and password (min. 8 characters). The database is created and seeded with sample categories, items and payment methods. |
| **3 · License** | Paste your license key to activate (needs internet once), or buy a license from inside the app. |
| **4 · Done** | A **cashier** account is created with a random password, shown once — write it down. |

<p align="center">
  <img src="docs/screenshots/setup-1-shop.png" width="32%" alt="Setup — shop">
  <img src="docs/screenshots/setup-2-admin.png" width="32%" alt="Setup — admin">
  <img src="docs/screenshots/license.png" width="32%" alt="License activation">
</p>

The license is bound to the device and protected against clock rollback; after activation the app never
needs the internet again.

---

## Daily workflow

**Cashier**
1. Log in → the cashier screen opens on the current shift.
2. Tap items (or scan barcodes), adjust quantities, choose the payment method.
3. Press **إتمام البيع وطباعة** (or <kbd>Enter</kbd>) → the receipt prints.
4. Use **الفواتير** (history) to reprint, and **المرتجعات** (returns) for a refund.

**Admin / owner**
1. **لوحة التحكم** — enter the shift's opening cash; live shift totals, items, stock and best sellers; add / edit items.
2. **المصروفات** — record expenses during the shift.
3. **التقارير** — today, last 7 days, payment methods, stock alerts, any date range.
4. **إغلاق الوردية** — review the summary, type the counted cash, add a note, close. A new shift starts with the next sale.
5. **النسخ الاحتياطي** — download a backup regularly (e.g. to a USB stick).

---

## Roles & permissions

| Capability | Cashier (`process_order`) | Admin (`manage_items` / role admin) |
|---|:---:|:---:|
| Sell, print, history, returns | ✅ | ✅ |
| Cancel an order | — | ✅ |
| Items, categories, payment methods, stock | — | ✅ |
| Expenses, reports, shift close & history | — | ✅ |
| Users, settings, backup / restore | — | ✅ |

Admins always have every permission; cashier accounts get the permissions ticked in **إدارة المستخدمين**.

---

## Keyboard shortcuts & barcode scanners

| Key | Action |
|---|---|
| <kbd>F2</kbd> | Focus the search / barcode box |
| <kbd>Enter</kbd> | In search: add the first match (or the exact barcode) · Elsewhere: checkout |
| <kbd>Esc</kbd> | Clear the cart |

Any USB barcode scanner that works as a keyboard (types the code + Enter) is supported — just set the
item's barcode in the admin screen.

---

## Where your data lives & backups

| Install type | Data folder |
|---|---|
| Windows installer | `C:\ProgramData\Cashirak POS\` |
| Portable / Linux / dev | the project folder (or `CASHIRAK_DATA_PATH`) |

Inside the data folder:

```
database/cashirak.sqlite      ← ALL your data (menu, orders, shifts, users, settings)
storage/installed.lock        ← marks the app as installed
storage/.license_data         ← activated license (device-bound)
storage/.device_id            ← this device's ID
storage/logs/php_errors.log   ← error log
```

**Backup:** Admin → النسخ الاحتياطي → *download*, or simply copy `database/cashirak.sqlite`.
**Restore:** upload a backup file on the same page; the current database is first saved as
`cashirak-before-restore-<date>.sqlite`. Moving to a new PC: install, restore the backup, activate the license.

---

## Security

- Passwords hashed with `password_hash()` (bcrypt); sessions are regenerated on login and every 30 minutes.
- **CSRF token** on every form and AJAX request; every page checks login + permission.
- Output escaped with `htmlspecialchars`; all SQL uses prepared statements.
- Order totals recalculated server-side; idempotency keys prevent double orders.
- Audit log for order creation / cancellation; returns are one-per-order and quantity-checked.
- The server listens on `127.0.0.1` only — it isn't reachable from the network.
- Change default passwords, keep the cashier password private and back up regularly.

---

## Project structure

```
cashirak-pos/
├── bootstrap.php            # boot: paths, autoloader, session, license gate
├── start.bat                # Windows launcher (free port, data folders, opens browser)
├── LaunchCashirakPOS.vbs    # runs start.bat without a console window
├── CashirakPOS.iss          # Inno Setup 6 installer script
├── app/
│   ├── Core/                # DB, Session, Auth, Security, Settings, License, Installer, paths
│   ├── Models/              # Item, Category, Order, OrderItem, Shift, User
│   ├── Repositories/        # OrderRepository (transactional order creation)
│   ├── Services/            # OrderService, ShiftService
│   ├── Middleware/          # AuthMiddleware (login, permission, CSRF)
│   ├── ElmahdiPay/          # license / payment SDK
│   ├── helpers.php          # getSetting() + schema migrations
│   └── version.php
├── public/                  # web root
│   ├── index.php            # cashier screen        ├── order.php       # order API (JSON)
│   ├── print.php            # receipt               ├── history.php     # shift orders
│   ├── returns.php          # returns               ├── shift-close.php # close & reconcile
│   ├── shift-history.php    # closed shifts         ├── shift-details.php
│   ├── admin.php            # dashboard + items     ├── install.php / license.php / login.php
│   ├── admin/               # categories, payment-methods, reports, expenses, users, settings, backup
│   └── assets/              # Bootstrap RTL, icons, logo (all local)
├── views/partials/          # header.php, footer.php
├── certs/                   # CA bundle + license public key
├── installer_assets/        # icon & installer wizard images
├── docs/                    # screenshots & marketing images
└── tools/screenshots/       # scripts that regenerate docs/
```

---

## Screenshots & marketing images (for the website)

Everything under [`docs/`](docs) is ready to use on [almhdy24.com](https://almhdy24.com), in stores or on social media:

| Folder | What | Size |
|---|---|---|
| [`docs/showcase/`](docs/showcase) | Presentation images: branded background, browser frame, Arabic title + English subtitle (2400×1500 PNG) | full quality |
| [`docs/showcase/webp/`](docs/showcase/webp) | Same images as WebP — **use these on the website** (~50–120 KB each) | web |
| `docs/showcase/hero.png` | Landing-page hero banner (2400×1350) | |
| `docs/showcase/social-preview.png` | 1280×640 card — upload in GitHub → Settings → *Social preview*, or use for Facebook/WhatsApp/X | |
| `docs/showcase/devices.png` | Tablet + phone mock-up | |
| [`docs/screenshots/`](docs/screenshots) | Raw 2× screenshots of every screen (desktop 1440×900, tablet, phone, receipt) | |
| [`docs/index.html`](docs/index.html) | A ready-made bilingual gallery page using the WebP images | |

### Regenerating them

The images are produced by scripts in [`tools/screenshots/`](tools/screenshots) from the real app, so they
can be refreshed after every UI change:

```bash
npm i -g playwright          # once (uses Chromium)
bash tools/screenshots/build.sh
```

`build.sh` copies the project to a temporary folder, runs the setup wizard, seeds a demo café
(`seed-demo.php` — Sudanese menu, a week of shifts, expenses, returns), captures every screen with Playwright
(`capture.js`), renders the presentation images (`showcase.js`) and converts them to WebP with ImageMagick.
Your real database and license are never touched. (In the temporary copy only, the license gate is skipped
and a placeholder license is shown on the settings page.)

---

## FAQ

**Does it really work without internet?**
Yes. Internet is needed once, to activate the license. After that, unplug the cable — selling, printing,
reports and backups all work locally.

**The power went out — did I lose sales?**
No. Every order is written to the database in a transaction the moment it's sold.

**Which printers work?**
Any printer installed in Windows/Linux, including 58/80 mm thermal receipt printers. The receipt uses the browser's print dialog.

**Can I use it on a tablet or phone?**
Yes — the layout is responsive and the buttons are touch-sized. Run the app on a PC and open it from the tablet's browser,
or run it directly on Android with Termux.

**How do I add a payment method or change the currency / receipt text?**
Admin → طرق الدفع for payment methods; Admin → الإعدادات for shop name, currency, receipt footer and date format.

**How do I move to a new computer?**
Make a backup, install Cashirak POS on the new PC, restore the backup and activate your license there.

**I forgot the cashier password.**
Log in as admin → المستخدمون → edit the cashier and set a new password.

---

## Support

Cashirak POS is commercial software by **Elmahdi Dev** — © 2026. A license key is required to use it.

- 🌐 Website: **[almhdy24.com](https://almhdy24.com)**
- 🐞 Issues & ideas: [GitHub issues](https://github.com/almhdy24/cashirak-pos/issues)

<div align="center"><sub>Built in Sudan 🇸🇩 for shops that need to keep selling — with or without internet.</sub></div>
