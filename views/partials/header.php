<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= htmlspecialchars($pageTitle ?? getSetting('cafe_title', 'كاشيراك')) ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/svg+xml" href="/assets/logo.svg">
    <link rel="stylesheet" href="/assets/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="/assets/bootstrap-icons.min.css">
    <style>
        /* ── Design tokens ───────────────────────────────────────────── */
        :root {
            --blue:         #2563eb;
            --blue-hover:   #1d4ed8;
            --blue-lt:      #eff6ff;
            --green:        #16a34a;
            --red:          #dc2626;
            --amber:        #d97706;
            --nav-bg:       #0f172a;
            --surface:      #fff;
            --bg:           #f1f5f9;
            --border:       rgba(0,0,0,.07);
            --text:         #1e293b;
            --muted:        #64748b;
            --radius-card:  12px;
            --radius-btn:   8px;
            --shadow-card:  0 1px 3px rgba(0,0,0,.06), 0 4px 20px rgba(0,0,0,.07);
            --shadow-lg:    0 8px 32px rgba(0,0,0,.12);
            --ease:         .16s ease;
        }

        /* ── Base ────────────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            background: var(--bg);
            font-family: 'Tahoma', 'Segoe UI', system-ui, sans-serif;
            color: var(--text);
            font-size: .94rem;
            min-height: 100vh;
        }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* ── Cards ───────────────────────────────────────────────────── */
        .card {
            border: 1px solid var(--border);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            background: var(--surface);
        }
        .card-header {
            padding: .85rem 1.25rem;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            font-size: .95rem;
        }
        .card-header.bg-dark  { background: #1e293b !important; color: #fff; }
        .card-header.bg-white { background: var(--surface) !important; }
        .card-header.bg-light { background: #f8fafc !important; }

        /* ── Buttons ─────────────────────────────────────────────────── */
        .btn {
            border-radius: var(--radius-btn);
            font-weight: 500;
            transition: background var(--ease), border-color var(--ease),
                        box-shadow var(--ease), transform var(--ease);
        }
        .btn:active { transform: scale(.97); }
        .btn-lg { border-radius: 10px; }
        .btn-primary  { background: var(--blue);  border-color: var(--blue); }
        .btn-primary:hover  { background: var(--blue-hover); border-color: var(--blue-hover); }
        .btn-success:hover  { background: #15803d; border-color: #15803d; }
        .btn-danger:hover   { background: #b91c1c; border-color: #b91c1c; }
        .btn-warning:hover  { background: #b45309; border-color: #b45309; }
        .btn-outline-primary { color: var(--blue); border-color: var(--blue); }
        .btn-outline-primary:hover { background: var(--blue); color: #fff; }

        /* ── Forms ───────────────────────────────────────────────────── */
        .form-control, .form-select {
            border-radius: var(--radius-btn);
            border: 1.5px solid #e2e8f0;
            color: var(--text);
            transition: border-color var(--ease), box-shadow var(--ease);
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37,99,235,.14);
        }
        .form-label {
            font-weight: 500;
            font-size: .875rem;
            color: var(--muted);
            margin-bottom: .3rem;
        }
        .form-text { font-size: .78rem; color: var(--muted); }
        .input-group .form-control { border-radius: var(--radius-btn); }

        /* ── Tables ──────────────────────────────────────────────────── */
        .table { font-size: .9rem; margin-bottom: 0; }
        .table-light th,
        thead.table-light th {
            font-size: .76rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            background: #f8fafc !important;
            padding: .65rem 1rem;
        }
        .table > :not(caption) > * > * { padding: .6rem 1rem; }
        .table-hover > tbody > tr:hover > * { background: #f0f9ff; }

        /* ── Alerts ──────────────────────────────────────────────────── */
        .alert {
            border-radius: 10px;
            border: none;
            font-size: .9rem;
            padding: .75rem 1rem;
        }
        .alert-success { background: #f0fdf4; color: #15803d; }
        .alert-danger  { background: #fef2f2; color: #b91c1c; }
        .alert-warning { background: #fffbeb; color: #92400e; }
        .alert-info    { background: #eff6ff; color: #1d4ed8; }
        .alert-secondary { background: #f8fafc; color: var(--muted); }

        /* ── Badges ──────────────────────────────────────────────────── */
        .badge { font-weight: 500; letter-spacing: .02em; }

        /* ── Modals ──────────────────────────────────────────────────── */
        .modal-content { border-radius: 14px; border: none; box-shadow: var(--shadow-lg); }
        .modal-header { border-bottom: 1px solid var(--border); padding: 1rem 1.25rem; }
        .modal-footer { border-top:   1px solid var(--border); padding: .85rem 1.25rem; }

        /* ── Page heading ────────────────────────────────────────────── */
        h2.mb-4 { font-size: 1.3rem; font-weight: 700; color: var(--text); }
        h2 i, h5 i { opacity: .75; }

        /* ── Page header row ─────────────────────────────────────────── */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .75rem;
            margin-bottom: 1.5rem;
        }
        .page-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; }

        /* ── Stat cards ──────────────────────────────────────────────── */
        .stat-card {
            background: var(--surface);
            border-radius: var(--radius-card);
            padding: 1.1rem 1.25rem;
            box-shadow: var(--shadow-card);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 1rem;
            height: 100%;
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .stat-icon.green  { background: #f0fdf4; color: #16a34a; }
        .stat-icon.blue   { background: #eff6ff; color: #2563eb; }
        .stat-icon.amber  { background: #fffbeb; color: #d97706; }
        .stat-icon.red    { background: #fef2f2; color: #dc2626; }
        .stat-icon.slate  { background: #f8fafc; color: #64748b; }
        .stat-icon.purple { background: #faf5ff; color: #7c3aed; }
        .stat-label { font-size: .74rem; color: var(--muted); font-weight: 500; margin-bottom: 2px; }
        .stat-value { font-size: 1.45rem; font-weight: 800; color: var(--text); line-height: 1.1; }
        .stat-sub   { font-size: .72rem; color: var(--muted); margin-top: 2px; }

        /* ── Empty state ─────────────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--muted);
        }
        .empty-state i { font-size: 2.5rem; opacity: .25; display: block; margin-bottom: .75rem; }
        .empty-state p { font-size: .9rem; margin: 0; }

        /* ── kbd ─────────────────────────────────────────────────────── */
        kbd {
            background: #1e293b; color: #e2e8f0;
            border-radius: 5px; padding: 2px 7px;
            font-size: .78rem; font-family: monospace; font-weight: 700;
        }

        /* ── List groups ─────────────────────────────────────────────── */
        .list-group-item { border-color: var(--border); font-size: .9rem; }

        /* ────────────────────────────────────────────────────────────── */
        /* POS-specific styles                                             */
        /* ────────────────────────────────────────────────────────────── */

        /* Item grid */
        #itemsContainer {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(138px, 1fr));
            gap: 10px;
        }

        /* Individual item card-button */
        .item-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            background: var(--surface);
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 8px 11px;
            min-height: 96px;
            cursor: pointer;
            transition: border-color var(--ease), background var(--ease),
                        box-shadow var(--ease), transform var(--ease), color var(--ease);
            text-align: center;
            color: var(--text);
            font-weight: 500;
            font-size: .875rem;
            line-height: 1.3;
            width: 100%;
        }
        .item-btn:hover {
            border-color: var(--blue);
            background: var(--blue-lt);
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(37,99,235,.18);
            color: var(--blue);
        }
        .item-btn:active { transform: scale(.96); }
        .item-btn .item-name { flex: 1; display: flex; align-items: center; justify-content: center; }
        .item-btn .item-price {
            font-size: .73rem;
            background: var(--blue);
            color: #fff;
            border-radius: 20px;
            padding: 2px 10px;
            margin-top: 7px;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .item-btn.out-of-stock {
            opacity: .38;
            cursor: not-allowed;
            border-style: dashed;
        }
        .item-btn.out-of-stock:hover {
            transform: none; box-shadow: none;
            border-color: #e2e8f0; background: var(--surface); color: var(--text);
        }
        @keyframes itemAdded {
            0%   { border-color: var(--green); background: #f0fdf4; box-shadow: 0 0 0 3px rgba(22,163,74,.25); }
            100% { border-color: #e2e8f0; background: var(--surface); box-shadow: none; }
        }
        .item-btn.just-added { animation: itemAdded .55s ease forwards; }

        /* Popular / best-seller pills */
        #popularContainer { display: flex; gap: 7px; flex-wrap: wrap; margin-bottom: 14px; }
        .pop-btn {
            border-radius: 50px;
            padding: 5px 14px;
            font-size: .83rem;
            font-weight: 600;
            border: 1.5px solid var(--green);
            color: var(--green);
            background: #f0fdf4;
            cursor: pointer;
            transition: all var(--ease);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .pop-btn:hover { background: var(--green); color: #fff; }
        .pop-btn:active { transform: scale(.96); }

        /* Category tabs — pill buttons */
        #categoryTabs {
            display: flex;
            gap: 5px;
            flex-wrap: nowrap;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding-bottom: 2px;
        }
        #categoryTabs::-webkit-scrollbar { display: none; }
        .cat-tab {
            flex-shrink: 0;
            border: 1.5px solid transparent;
            border-radius: 8px;
            padding: .45rem 1rem;
            font-weight: 600;
            font-size: .83rem;
            color: var(--muted);
            background: var(--surface);
            white-space: nowrap;
            cursor: pointer;
            transition: color var(--ease), background var(--ease), box-shadow var(--ease), border-color var(--ease);
        }
        .cat-tab:hover { color: var(--blue); background: var(--blue-lt); border-color: #bfdbfe; }
        .cat-tab.active {
            background: var(--blue);
            color: #fff;
            border-color: transparent;
            box-shadow: 0 2px 8px rgba(37,99,235,.28);
        }

        /* Search input */
        .search-wrap { position: relative; }
        .search-wrap .search-icon {
            position: absolute; right: .8rem; top: 50%;
            transform: translateY(-50%);
            color: #94a3b8; pointer-events: none; font-size: .9rem;
        }
        .search-wrap input { padding-right: 2.4rem; }

        /* Cart panel */
        .cart-container {
            background: var(--surface);
            border-radius: 16px;
            padding: 18px;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 68px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .cart-header { display: flex; align-items: center; }
        .cart-header h5 { font-size: 1rem; font-weight: 700; color: var(--text); margin: 0; }

        #cart-items { overflow-y: auto; max-height: 300px; min-height: 130px; }

        .cart-item {
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: .88rem;
        }
        .cart-item:last-of-type { border-bottom: none; }
        .cart-item-name { font-weight: 500; color: var(--text); line-height: 1.3; }
        .cart-item-sub  { font-size: .82rem; font-weight: 700; color: var(--blue); white-space: nowrap; }

        /* Qty buttons — large enough for tablet touch */
        .qty-btn {
            width: 36px; height: 36px; padding: 0;
            border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.1rem; line-height: 1; font-weight: 700;
            transition: all var(--ease);
        }
        .qty-val { font-weight: 700; font-size: 1rem; min-width: 28px; text-align: center; }

        /* Total box */
        .total-box {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
            color: #fff;
            border-radius: 14px;
            padding: 14px 16px;
            text-align: center;
        }
        .total-label {
            font-size: .68rem;
            opacity: .6;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 3px;
        }
        .total-value {
            font-size: 1.9rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -.01em;
        }
        .total-currency { font-size: .9rem; opacity: .75; font-weight: 500; }

        /* Payment method buttons */
        .pay-methods-wrap { display: flex; gap: 6px; flex-wrap: wrap; }
        .pay-method-btn {
            flex: 1;
            min-width: 72px;
            padding: .5rem .4rem;
            border-radius: 8px;
            border: 1.5px solid #e2e8f0;
            background: var(--surface);
            color: var(--muted);
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--ease);
            text-align: center;
            white-space: nowrap;
        }
        .pay-method-btn:hover { border-color: var(--blue); color: var(--blue); }
        .pay-method-btn.selected {
            border-color: var(--blue);
            background: var(--blue);
            color: #fff;
            box-shadow: 0 2px 8px rgba(37,99,235,.25);
        }

        /* Toast notifications */
        #posToastContainer {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            display: flex;
            flex-direction: column-reverse;
            gap: .5rem;
            pointer-events: none;
            align-items: center;
        }
        .pos-toast {
            padding: .7rem 1.1rem;
            border-radius: 10px;
            font-size: .88rem;
            font-weight: 500;
            color: #fff;
            box-shadow: 0 4px 24px rgba(0,0,0,.22);
            display: flex;
            align-items: center;
            gap: .55rem;
            opacity: 0;
            transform: translateY(10px) scale(.97);
            transition: opacity .2s ease, transform .2s ease;
            white-space: nowrap;
        }
        .pos-toast.show { opacity: 1; transform: translateY(0) scale(1); }
        .pos-toast.toast-success { background: #059669; }
        .pos-toast.toast-warning { background: #d97706; }
        .pos-toast.toast-danger  { background: #dc2626; }
        .pos-toast.toast-info    { background: #2563eb; }

        /* ── Navbar ──────────────────────────────────────────────────── */
        .app-navbar {
            background: var(--nav-bg);
            padding: 0 1.25rem;
            position: sticky;
            top: 0;
            z-index: 1030;
            box-shadow: 0 2px 14px rgba(0,0,0,.3);
        }
        .app-navbar .navbar-brand {
            color: #fff;
            font-weight: 700;
            font-size: .95rem;
            padding: .65rem 0;
            gap: .5rem;
            display: flex;
            align-items: center;
        }
        .app-navbar .navbar-brand img { height: 26px; }
        .app-navbar .nav-link {
            color: rgba(255,255,255,.6) !important;
            font-size: .875rem;
            padding: .85rem .75rem !important;
            border-bottom: 3px solid transparent;
            transition: color var(--ease), border-color var(--ease);
        }
        .app-navbar .nav-link:hover { color: #fff !important; }
        .app-navbar .nav-link.active { color: #fff !important; border-bottom-color: #3b82f6; }
        .app-navbar .dropdown-menu {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 10px;
            min-width: 215px;
            padding: .4rem;
            box-shadow: 0 10px 36px rgba(0,0,0,.4);
            margin-top: 2px !important;
        }
        .app-navbar .dropdown-item {
            color: rgba(255,255,255,.72);
            font-size: .875rem;
            padding: .5rem .9rem;
            border-radius: 7px;
            transition: background var(--ease), color var(--ease);
        }
        .app-navbar .dropdown-item:hover,
        .app-navbar .dropdown-item.active {
            background: rgba(59,130,246,.22);
            color: #fff;
        }
        .app-navbar .dropdown-divider { border-color: rgba(255,255,255,.08); margin: .3rem 0; }
        .app-navbar .navbar-toggler { border-color: rgba(255,255,255,.25); padding: .35rem .55rem; }
        .app-navbar .navbar-toggler-icon { filter: invert(1); width: 1.1rem; height: 1.1rem; }
        .app-navbar .user-info {
            color: rgba(255,255,255,.5);
            font-size: .8rem;
            padding: .85rem .5rem;
            display: flex; align-items: center; gap: .4rem;
        }
        .app-navbar .user-info .badge { font-size: .67rem; }
        .app-navbar .btn-logout {
            color: #f87171 !important;
            padding: .85rem .75rem !important;
            transition: color var(--ease);
        }
        .app-navbar .btn-logout:hover { color: #fca5a5 !important; }

        /* ── Mobile / tablet ─────────────────────────────────────────── */
        @media (max-width: 991px) {
            #itemsContainer { grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); }
            .item-btn { min-height: 88px; }
        }
        @media (max-width: 767px) {
            #itemsContainer {
                grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
                gap: 7px;
            }
            .item-btn { min-height: 80px; font-size: .82rem; padding: 10px 6px 8px; }
            .item-btn .item-price { font-size: .7rem; padding: 2px 8px; }
            .cart-container { position: relative; top: 0; }
            .total-value { font-size: 1.6rem; }
            h2.mb-4 { font-size: 1.15rem; }
        }
    </style>
</head>
<body>

<?php
/* ── Active-link helpers ───────────────────────────────────────── */
$_navFile    = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
$_inAdminDir = (basename(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'admin');
$_isAdminArea = $_inAdminDir || $_navFile === 'admin.php';

function _nav_active(string $file): string {
    global $_navFile, $_inAdminDir;
    return (!$_inAdminDir && $_navFile === $file) ? 'active' : '';
}
function _nav_admin_file(string $file): string {
    global $_navFile, $_inAdminDir;
    return ($_inAdminDir && $_navFile === $file) ? 'active' : '';
}

$_navUser = \Core\Auth::user();
?>

<nav class="navbar navbar-expand-md app-navbar">
    <div class="container-fluid px-0">

        <a class="navbar-brand" href="/index.php">
            <img src="/assets/logo.svg" alt="">
            <?= htmlspecialchars(getSetting('cafe_title', 'كاشيراك')) ?>
        </a>

        <button class="navbar-toggler ms-auto" type="button"
                data-bs-toggle="collapse" data-bs-target="#appNav"
                aria-controls="appNav" aria-expanded="false">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="appNav">

            <ul class="navbar-nav me-3">
                <li class="nav-item">
                    <a class="nav-link <?= _nav_active('index.php') ?>" href="/index.php">
                        <i class="bi bi-cart3"></i> الكاشير
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= _nav_active('history.php') ?>" href="/history.php">
                        <i class="bi bi-receipt"></i> الفواتير
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= _nav_active('returns.php') ?>" href="/returns.php">
                        <i class="bi bi-arrow-return-right"></i> المرتجعات
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= _nav_active('shift-close.php') ?>" href="/shift-close.php">
                        <i class="bi bi-door-closed"></i> إغلاق الوردية
                    </a>
                </li>
            </ul>

            <?php if ($_navUser && \Core\Auth::hasPermission('manage_items')): ?>
            <ul class="navbar-nav me-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $_isAdminArea ? 'active' : '' ?>"
                       href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                        <i class="bi bi-speedometer2"></i> الإدارة
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?= _nav_active('admin.php') ?>" href="/admin.php">
                            <i class="bi bi-box-seam"></i> الأصناف والمخزون</a></li>
                        <li><a class="dropdown-item <?= _nav_admin_file('categories.php') ?>" href="/admin/categories.php">
                            <i class="bi bi-tags"></i> التصنيفات</a></li>
                        <li><a class="dropdown-item <?= _nav_admin_file('payment-methods.php') ?>" href="/admin/payment-methods.php">
                            <i class="bi bi-credit-card"></i> طرق الدفع</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item <?= _nav_admin_file('reports.php') ?>" href="/admin/reports.php">
                            <i class="bi bi-bar-chart-line"></i> التقارير</a></li>
                        <li><a class="dropdown-item <?= _nav_admin_file('expenses.php') ?>" href="/admin/expenses.php">
                            <i class="bi bi-cash-coin"></i> المصروفات</a></li>
                        <li><a class="dropdown-item <?= _nav_active('shift-history.php') ?>" href="/shift-history.php">
                            <i class="bi bi-calendar-check"></i> تاريخ الورديات</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item <?= _nav_admin_file('users.php') ?>" href="/admin/users.php">
                            <i class="bi bi-people"></i> المستخدمون</a></li>
                        <li><a class="dropdown-item <?= _nav_admin_file('settings.php') ?>" href="/admin/settings.php">
                            <i class="bi bi-gear"></i> الإعدادات</a></li>
                        <li><a class="dropdown-item <?= _nav_admin_file('backup.php') ?>" href="/admin/backup.php">
                            <i class="bi bi-cloud-arrow-down"></i> النسخ الاحتياطي</a></li>
                    </ul>
                </li>
            </ul>
            <?php else: ?>
            <div class="me-auto"></div>
            <?php endif; ?>

            <ul class="navbar-nav align-items-center">
                <?php if ($_navUser): ?>
                <li class="nav-item">
                    <span class="user-info">
                        <i class="bi bi-person-circle"></i>
                        <?= htmlspecialchars($_navUser['username']) ?>
                        <span class="badge <?= $_navUser['role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>">
                            <?= $_navUser['role'] === 'admin' ? 'مدير' : 'كاشير' ?>
                        </span>
                    </span>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link btn-logout" href="/logout.php" title="تسجيل الخروج">
                        <i class="bi bi-box-arrow-right"></i> خروج
                    </a>
                </li>
            </ul>

        </div>
    </div>
</nav>

<div class="container-fluid p-3">
