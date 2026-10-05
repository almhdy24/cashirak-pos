<?php
// install.php – معالج التثبيت متعدد الخطوات
session_start();

require_once __DIR__ . '/../app/Core/paths.php';   // defines STORAGE_PATH, DB_PATH
require_once __DIR__ . '/../app/Core/DB.php';
require_once __DIR__ . '/../app/Core/Security.php';
require_once __DIR__ . '/../app/Core/Installer.php';

// Create the data folders on first run (start.bat does this on Windows; Linux/macOS need it here)
foreach ([STORAGE_PATH, STORAGE_PATH . '/logs', STORAGE_PATH . '/sessions', STORAGE_PATH . '/backups', dirname(DB_PATH)] as $_dir) {
    if (!is_dir($_dir)) @mkdir($_dir, 0777, true);
}
unset($_dir);

use Core\Installer;

// Already installed + licensed → login
$installed = file_exists(STORAGE_PATH . '/installed.lock');
$licensed  = file_exists(STORAGE_PATH . '/.license_data') &&
             (json_decode(@file_get_contents(STORAGE_PATH . '/.license_data'), true)['status'] ?? '') === 'active';

if ($installed && $licensed) {
    header('Location: login.php');
    exit;
}

// If installed but not yet licensed, skip install steps
if ($installed && !$licensed) {
    header('Location: license.php?wizard=1');
    exit;
}

// ── Step tracking ─────────────────────────────────────────────────────────────
// Reset only when session carries a stale step from a previous completed run
$_staleStep = (int)($_SESSION['wizard_step'] ?? 0);
if (!$installed && $_staleStep > 2) {
    unset($_SESSION['wizard_step'], $_SESSION['wizard_shop'], $_SESSION['wizard_cashier_pass']);
}
unset($_staleStep);

$step  = (int)($_GET['step'] ?? $_SESSION['wizard_step'] ?? 1);
$error = '';

$installer    = new Installer();
$requirements = $installer->checkRequirements();
// Only hard-block on the minimum needed to run. curl+openssl are needed
// for license activation (warned below) but don't block the wizard itself.
$allGood      = $requirements['php'] && $requirements['sqlite'] && $requirements['storage'];

if ($step === 1 && !$allGood) {
    // Stay on step 1 until requirements pass
}

// CSRF for wizard forms
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['install_csrf'];

// ── Handle POSTs ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['csrf_token'] ?? '') !== $csrf) {
        die('رمز الأمان غير صحيح. يرجى تحديث الصفحة.');
    }

    switch ($step) {
        case 1: // Save shop data → step 2
            $shopData = [
                'cafe_title'     => trim($_POST['cafe_title']     ?? 'كاشيراك'),
                'currency'       => trim($_POST['currency']       ?? 'SDG'),
                'invoice_footer' => trim($_POST['invoice_footer'] ?? 'شكراً لزيارتكم'),
                'date_format'    => trim($_POST['date_format']    ?? 'Y-m-d H:i:s'),
            ];
            if (mb_strlen($shopData['cafe_title'], 'UTF-8') < 2) {
                $error = 'اسم المحل يجب أن يكون حرفين على الأقل';
                break;
            }
            $_SESSION['wizard_shop'] = $shopData;
            $_SESSION['wizard_step'] = 2;
            header('Location: install.php?step=2');
            exit;

        case 2: // Save admin + install → redirect to license
            $adminData = [
                'admin_username'         => trim($_POST['admin_username'] ?? ''),
                'admin_password'         => $_POST['admin_password']         ?? '',
                'admin_password_confirm' => $_POST['admin_password_confirm'] ?? '',
            ];
            $allData = array_merge($_SESSION['wizard_shop'] ?? [], $adminData);
            $result  = $installer->install($allData);

            if (!$result['success']) {
                $error = $result['message'];
                break;
            }

            // Save cashier password for display on finish
            $_SESSION['wizard_cashier_pass'] = $result['cashier_password'];
            $_SESSION['wizard_step'] = 3;

            header('Location: license.php?wizard=1');
            exit;
    }
}

// ── View helpers ─────────────────────────────────────────────────────────────
function stepDot(int $n, int $current): string {
    if ($n < $current) return '<span class="step-dot done"><i class="bi bi-check-lg"></i></span>';
    if ($n === $current) return '<span class="step-dot active">' . $n . '</span>';
    return '<span class="step-dot">' . $n . '</span>';
}

$shopDefaults = $_SESSION['wizard_shop'] ?? [
    'cafe_title'     => 'كاشيراك',
    'currency'       => 'SDG',
    'invoice_footer' => 'شكراً لزيارتكم',
    'date_format'    => 'Y-m-d H:i:s',
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تثبيت كاشيراك</title>
    <link rel="stylesheet" href="/assets/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="/assets/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Tahoma', 'Segoe UI', system-ui;
            padding: 1.5rem;
        }
        .wizard-card {
            background: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 25px 60px rgba(0,0,0,.4);
            width: 100%;
            max-width: 580px;
            padding: 2.5rem 2.5rem 2rem;
        }
        .logo-wrap { text-align: center; margin-bottom: .75rem; }
        .logo-wrap img { height: 64px; }
        h1.wizard-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1a1a2e;
            text-align: center;
            margin-bottom: .25rem;
        }
        .wizard-subtitle { text-align: center; color: #6c757d; margin-bottom: 1.5rem; }

        /* Step dots */
        .step-track { display: flex; align-items: center; justify-content: center; gap: .35rem; margin-bottom: 2rem; }
        .step-dot {
            width: 34px; height: 34px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .9rem;
            background: #e9ecef; color: #6c757d;
        }
        .step-dot.active  { background: #6c63ff; color: #fff; }
        .step-dot.done    { background: #198754; color: #fff; }
        .step-line { flex: 1; max-width: 40px; height: 2px; background: #dee2e6; }

        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid #dee2e6;
            padding: .65rem 1rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #6c63ff;
            box-shadow: 0 0 0 .2rem rgba(108,99,255,.2);
        }
        .btn-primary { background: #6c63ff; border-color: #6c63ff; border-radius: 50px; }
        .btn-primary:hover { background: #5a52d5; }

        .req-item { display: flex; gap: .5rem; align-items: center; padding: .4rem 0; border-bottom: 1px solid #f0f0f0; }
        .req-item:last-child { border-bottom: none; }

        .footer-small { text-align: center; color: #adb5bd; font-size: .82rem; margin-top: 1.2rem; }
        .footer-small a { color: #6c63ff; text-decoration: none; }
    </style>
</head>
<body>
<div class="wizard-card">
    <div class="logo-wrap">
        <img src="/assets/logo.svg" alt="كاشيراك">
    </div>
    <h1 class="wizard-title">كاشيراك</h1>
    <p class="wizard-subtitle">معالج الإعداد الأولي</p>

    <!-- Step indicator -->
    <div class="step-track">
        <?= stepDot(1, $step) ?>
        <span class="step-line"></span>
        <?= stepDot(2, $step) ?>
        <span class="step-line"></span>
        <?= stepDot(3, $step) ?>
        <span class="step-line"></span>
        <?= stepDot(4, $step) ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
    <!-- ── Step 1: Requirements + Shop Setup ─────────────────────────────── -->
    <h5 class="mb-3"><i class="bi bi-shop"></i> إعداد المحل</h5>

    <!-- Requirements check (inline) -->
    <div class="mb-3 p-3 bg-light rounded-3">
        <div class="req-item">
            <i class="bi <?= $requirements['php']     ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i>
            <span>PHP 8.0+ <small class="text-muted">(<?= PHP_VERSION ?>)</small></span>
        </div>
        <div class="req-item">
            <i class="bi <?= $requirements['sqlite']  ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i>
            <span>SQLite (PDO)</span>
        </div>
        <div class="req-item">
            <i class="bi <?= $requirements['openssl'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i>
            <span>OpenSSL</span>
        </div>
        <div class="req-item">
            <i class="bi <?= $requirements['curl']    ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i>
            <span>cURL</span>
        </div>
        <div class="req-item">
            <i class="bi <?= $requirements['storage'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i>
            <span>مجلد التخزين قابل للكتابة</span>
        </div>
    </div>

    <?php if (!$allGood): ?>
        <div class="alert alert-danger">
            <i class="bi bi-x-circle-fill"></i>
            يرجى إصلاح المشاكل المعلمة بـ ✗ قبل المتابعة.
        </div>
    <?php else: ?>
    <?php if (!$requirements['curl'] || !$requirements['openssl']): ?>
        <div class="alert alert-warning py-2 small">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>تنبيه:</strong> cURL أو OpenSSL غير مفعّل. التثبيت يعمل لكن تفعيل الترخيص يتطلبهما.
        </div>
    <?php endif; ?>
    <form method="post" action="install.php?step=1">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="mb-3">
            <label class="form-label fw-semibold">اسم المحل / المقهى</label>
            <input type="text" name="cafe_title" class="form-control"
                   value="<?= htmlspecialchars($shopDefaults['cafe_title']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">رمز العملة</label>
            <input type="text" name="currency" class="form-control"
                   value="<?= htmlspecialchars($shopDefaults['currency']) ?>" required maxlength="10">
            <div class="form-text">الافتراضي: SDG</div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">تذييل الفاتورة</label>
            <input type="text" name="invoice_footer" class="form-control"
                   value="<?= htmlspecialchars($shopDefaults['invoice_footer']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">صيغة التاريخ</label>
            <input type="text" name="date_format" class="form-control"
                   value="<?= htmlspecialchars($shopDefaults['date_format']) ?>">
            <div class="form-text">مثال: Y-m-d H:i:s &nbsp;|&nbsp; d/m/Y h:i A</div>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
            التالي <i class="bi bi-arrow-left"></i>
        </button>
    </form>
    <?php endif; ?>

    <?php elseif ($step === 2): ?>
    <!-- ── Step 2: Admin Account ──────────────────────────────────────────── -->
    <h5 class="mb-3"><i class="bi bi-person-lock"></i> حساب المدير</h5>
    <form method="post" action="install.php?step=2">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="mb-3">
            <label class="form-label fw-semibold">اسم المستخدم</label>
            <input type="text" name="admin_username" class="form-control"
                   value="admin" required pattern="[A-Za-z0-9_]{3,32}"
                   title="3-32 حرف: أحرف إنجليزية، أرقام، شرطة سفلية">
            <div class="form-text">أحرف إنجليزية وأرقام فقط (3-32 حرفاً)</div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">كلمة المرور</label>
            <input type="password" name="admin_password" class="form-control"
                   minlength="8" autocomplete="new-password" required>
            <div class="form-text">8 أحرف على الأقل</div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">تأكيد كلمة المرور</label>
            <input type="password" name="admin_password_confirm" class="form-control"
                   minlength="8" autocomplete="new-password" required>
        </div>
        <div class="d-flex gap-2">
            <a href="install.php?step=1" class="btn btn-outline-secondary flex-fill py-2">
                <i class="bi bi-arrow-right"></i> السابق
            </a>
            <button type="submit" class="btn btn-primary flex-fill py-2 fw-semibold">
                تثبيت <i class="bi bi-download"></i>
            </button>
        </div>
    </form>
    <?php endif; ?>

    <div class="footer-small">
        © <?= date('Y') ?> <a href="https://almhdy24.com" target="_blank">Elmahdi Dev</a>
    </div>
</div>
</body>
</html>
