<?php
// license.php – صفحة تفعيل الترخيص
require_once __DIR__ . '/../bootstrap.php';
// Note: bootstrap.php calls License::requireActive() but license.php is excluded from that check

use Core\License;
use Core\Security;

$isWizard     = isset($_GET['wizard']);
$cashierPass  = $_SESSION['wizard_cashier_pass'] ?? '';
$info         = License::getInfo();
$deviceId     = License::deviceId();

$csrf = Security::generateCSRFToken();

$successMsg = '';
$errorMsg   = '';
$tab        = $_GET['tab'] ?? 'activate'; // 'activate' | 'purchase'

// ── AJAX endpoints ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (($_POST['csrf_token'] ?? '') !== Security::generateCSRFToken() &&
        ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') !== Security::generateCSRFToken()) {
        echo json_encode(['ok' => false, 'msg' => 'رمز الأمان غير صحيح']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Activate license key
    if ($action === 'activate') {
        try {
            $key    = trim($_POST['license_key'] ?? '');
            $result = License::activate($key);

            $msgs = [
                'activated'      => ['ok' => true,  'msg' => 'تم تفعيل الترخيص بنجاح! جارٍ إعادة التوجيه...'],
                'already_active' => ['ok' => true,  'msg' => 'الترخيص نشط بالفعل على هذا الجهاز.'],
                'limit_reached'  => ['ok' => false, 'msg' => 'تجاوز الحد الأقصى للأجهزة المسموح بها.'],
                'revoked'        => ['ok' => false, 'msg' => 'تم إلغاء هذا الترخيص.'],
                'expired'        => ['ok' => false, 'msg' => 'انتهت صلاحية هذا الترخيص.'],
                'invalid'        => ['ok' => false, 'msg' => 'مفتاح الترخيص غير صحيح أو غير موجود.'],
                'error'          => ['ok' => false, 'msg' => 'خطأ في الاتصال: ' . ($result['message'] ?? '')],
            ];

            $r = $msgs[$result['result']] ?? ['ok' => false, 'msg' => 'نتيجة غير متوقعة: ' . $result['result']];
        } catch (\Throwable $e) {
            $r = ['ok' => false, 'msg' => 'خطأ غير متوقع: ' . $e->getMessage()];
        }
        echo json_encode($r);
        exit;
    }

    // Get payment methods for purchase tab
    if ($action === 'get_methods') {
        $methods = License::getPaymentMethods();
        echo json_encode(['ok' => true, 'methods' => $methods]);
        exit;
    }

    // Create purchase order
    if ($action === 'create_order') {
        if (empty($_FILES['proof_file']['tmp_name'])) {
            echo json_encode(['ok' => false, 'msg' => 'صورة إثبات الدفع مطلوبة']);
            exit;
        }

        $uploadDir = STORAGE_PATH . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext      = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
        $tmpPath  = $uploadDir . 'proof_' . bin2hex(random_bytes(8)) . '.' . $ext;

        if (!move_uploaded_file($_FILES['proof_file']['tmp_name'], $tmpPath)) {
            echo json_encode(['ok' => false, 'msg' => 'فشل رفع الملف']);
            exit;
        }

        try {
            $idempotencyKey = $_SESSION['purchase_idem_key'] ?? null;
            if (!$idempotencyKey) {
                $idempotencyKey = \ElmahdiPay\ElmahdiPay::generateIdempotencyKey();
                $_SESSION['purchase_idem_key'] = $idempotencyKey;
            }

            $order = License::createPurchaseOrder([
                'buyer_name'        => trim($_POST['buyer_name']    ?? ''),
                'buyer_contact'     => trim($_POST['buyer_contact'] ?? ''),
                'amount'            => 500000,
                'currency'          => 'SDG',
                'payment_method_id' => (int)($_POST['payment_method_id'] ?? 0),
                'proof_image_path'  => $tmpPath,
                'proof_note'        => trim($_POST['proof_note'] ?? ''),
                'idempotency_key'   => $idempotencyKey,
            ]);

            $_SESSION['purchase_reference'] = $order['reference'];
            @unlink($tmpPath);

            echo json_encode([
                'ok'        => true,
                'reference' => $order['reference'],
                'status'    => $order['status'],
                'idempotent'=> $order['idempotent'],
            ]);
        } catch (\Throwable $e) {
            @unlink($tmpPath);
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    // Poll order status
    if ($action === 'check_order') {
        $ref = trim($_POST['reference'] ?? $_SESSION['purchase_reference'] ?? '');
        if (!$ref) {
            echo json_encode(['ok' => false, 'msg' => 'رقم الطلب مفقود']);
            exit;
        }
        try {
            $data = License::checkPurchaseStatus($ref);
            $result = ['ok' => true, 'status' => $data['status'] ?? 'unknown'];

            if (($data['status'] ?? '') === 'approved' && isset($data['license']['license_key'])) {
                // Auto-activate
                $activation = License::activate($data['license']['license_key']);
                $result['activated'] = in_array($activation['result'], ['activated', 'already_active']);
                $result['license_key'] = $data['license']['license_key'];
                if ($result['activated']) {
                    unset($_SESSION['purchase_reference'], $_SESSION['purchase_idem_key']);
                }
            }

            if (isset($data['admin_note'])) {
                $result['admin_note'] = $data['admin_note'];
            }

            echo json_encode($result);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'إجراء غير معروف']);
    exit;
}

$isActive = License::isActive();
$pageTitle = 'ترخيص كاشيراك';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/logo.svg">
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
        .lic-card {
            background: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 25px 60px rgba(0,0,0,.4);
            width: 100%;
            max-width: 600px;
            padding: 2.5rem;
        }
        .logo-wrap { text-align: center; margin-bottom: .5rem; }
        .logo-wrap img { height: 56px; }
        h1.lic-title { font-size: 1.6rem; font-weight: 700; color: #1a1a2e; text-align: center; }
        .lic-sub { text-align: center; color: #6c757d; margin-bottom: 1.5rem; }

        .step-track { display: flex; align-items: center; justify-content: center; gap: .35rem; margin-bottom: 1.75rem; }
        .step-dot {
            width: 34px; height: 34px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .9rem;
            background: #e9ecef; color: #6c757d;
        }
        .step-dot.active { background: #6c63ff; color: #fff; }
        .step-dot.done   { background: #198754; color: #fff; }
        .step-line { flex: 1; max-width: 40px; height: 2px; background: #dee2e6; }

        .price-tag {
            background: #f8f5ff;
            border: 2px solid #6c63ff;
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .price-tag .price { font-size: 2rem; font-weight: 800; color: #6c63ff; }

        .form-control, .form-select { border-radius: 10px; border: 1px solid #dee2e6; }
        .form-control:focus, .form-select:focus { border-color: #6c63ff; box-shadow: 0 0 0 .2rem rgba(108,99,255,.2); }
        .btn-purple { background: #6c63ff; border-color: #6c63ff; color: #fff; border-radius: 50px; }
        .btn-purple:hover { background: #5a52d5; color: #fff; }

        .cashier-pass { font-family: monospace; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: .5rem 1rem; font-size: 1.1rem; font-weight: bold; }

        .device-id { font-family: monospace; font-size: .8rem; color: #6c757d; word-break: break-all; }
        .footer-small { text-align: center; color: #adb5bd; font-size: .82rem; margin-top: 1.25rem; }
        .footer-small a { color: #6c63ff; text-decoration: none; }

        #statusSpinner { display: none; }
    </style>
</head>
<body>
<div class="lic-card">
    <div class="logo-wrap"><img src="/assets/logo.svg" alt="كاشيراك"></div>
    <h1 class="lic-title">ترخيص كاشيراك</h1>
    <p class="lic-sub">نظام نقاط البيع التجاري</p>

    <?php if ($isWizard): ?>
    <div class="step-track">
        <span class="step-dot done"><i class="bi bi-check-lg"></i></span>
        <span class="step-line"></span>
        <span class="step-dot done"><i class="bi bi-check-lg"></i></span>
        <span class="step-line"></span>
        <span class="step-dot active">3</span>
        <span class="step-line"></span>
        <span class="step-dot">4</span>
    </div>
    <?php endif; ?>

    <?php if ($isActive): ?>
    <!-- Already active -->
    <div class="alert alert-success text-center">
        <i class="bi bi-patch-check-fill fs-4"></i>
        <div class="fw-bold mt-1">الترخيص نشط</div>
        <small class="text-muted">الجهاز: <?= htmlspecialchars($deviceId) ?></small>
    </div>
    <div class="text-center mt-3">
        <a href="login.php" class="btn btn-success btn-lg rounded-pill px-4">
            <i class="bi bi-box-arrow-in-right"></i> الدخول إلى النظام
        </a>
    </div>

    <?php else: ?>
    <!-- Cashier password display (if coming from wizard) -->
    <?php if ($cashierPass && $isWizard): ?>
    <div class="alert alert-info mb-3">
        <i class="bi bi-info-circle-fill"></i>
        <strong>تم إنشاء حساب الكاشير</strong> — احفظ كلمة المرور الآن:<br>
        <div class="cashier-pass mt-2 text-center">
            اسم المستخدم: <strong>cashier</strong> &nbsp;|&nbsp; كلمة المرور: <strong><?= htmlspecialchars($cashierPass) ?></strong>
        </div>
        <small class="text-muted d-block mt-1">لن تظهر هذه الكلمة مرة أخرى. يمكنك تغييرها لاحقاً من لوحة الإدارة.</small>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-pills mb-3 justify-content-center" id="licTab">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'activate' ? 'active' : '' ?>"
               href="?<?= $isWizard ? 'wizard=1&' : '' ?>tab=activate">
                <i class="bi bi-key-fill"></i> لدي مفتاح ترخيص
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'purchase' ? 'active' : '' ?>"
               href="?<?= $isWizard ? 'wizard=1&' : '' ?>tab=purchase">
                <i class="bi bi-cart-check-fill"></i> شراء ترخيص
            </a>
        </li>
    </ul>

    <div id="msgBox"></div>

    <?php if ($tab === 'activate'): ?>
    <!-- ── Activate existing key ─────────────────────────────────────────── -->
    <div class="mb-3">
        <label class="form-label fw-semibold"><i class="bi bi-key"></i> مفتاح الترخيص</label>
        <input type="text" id="licKey" class="form-control font-monospace"
               placeholder="أدخل مفتاح الترخيص هنا..." dir="ltr" autocomplete="off">
    </div>
    <div class="mb-3">
        <small class="text-muted device-id"><i class="bi bi-cpu"></i> معرّف الجهاز: <?= htmlspecialchars($deviceId) ?></small>
    </div>
    <button id="activateBtn" class="btn btn-purple w-100 py-2 fw-semibold">
        <i class="bi bi-patch-check"></i> تفعيل الترخيص
    </button>

    <?php else: ?>
    <!-- ── Purchase new license ──────────────────────────────────────────── -->
    <div class="price-tag">
        <div class="text-muted small mb-1">سعر الترخيص التجاري</div>
        <div class="price">500,000 <span style="font-size:1.2rem;">SDG</span></div>
    </div>

    <!-- Purchase form -->
    <div id="purchaseForm">
        <div class="mb-3">
            <label class="form-label fw-semibold">الاسم الكامل</label>
            <input type="text" id="buyerName" class="form-control" placeholder="أحمد محمد علي" required>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">البريد الإلكتروني / تيليغرام / الهاتف</label>
            <input type="text" id="buyerContact" class="form-control" placeholder="contact@example.com" dir="ltr">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">طريقة الدفع</label>
            <select id="payMethod" class="form-select">
                <option value="">جارٍ التحميل...</option>
            </select>
            <div id="methodInstructions" class="form-text text-muted mt-2"></div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">صورة إثبات الدفع <span class="text-danger">*</span></label>
            <input type="file" id="proofFile" class="form-control" accept="image/*">
            <div class="form-text">JPG, PNG, WebP — حجم أقصى 5 ميجابايت</div>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">ملاحظة (اختياري)</label>
            <input type="text" id="proofNote" class="form-control" placeholder="رقم العملية أو ملاحظة للمراجعة">
        </div>
        <button id="purchaseBtn" class="btn btn-purple w-100 py-2 fw-semibold">
            <i class="bi bi-send-fill"></i> إرسال طلب الشراء
        </button>
    </div>

    <!-- Order status polling section (shown after order creation) -->
    <div id="orderStatus" style="display:none;">
        <div class="alert alert-info">
            <i class="bi bi-hourglass-split"></i>
            <strong>تم إرسال الطلب بنجاح!</strong><br>
            رقم الطلب: <strong id="orderRef" dir="ltr"></strong><br>
            <small class="text-muted">يتم مراجعة الطلب يدوياً. سيتم إرسال مفتاح الترخيص تلقائياً عند الموافقة.</small>
        </div>
        <div class="text-center mb-3">
            <div id="statusSpinner" class="spinner-border text-primary" role="status">
                <span class="visually-hidden">جارٍ التحقق...</span>
            </div>
            <span id="statusText" class="text-muted">جارٍ التحقق من حالة الطلب...</span>
        </div>
        <button id="checkStatusBtn" class="btn btn-outline-primary w-100">
            <i class="bi bi-arrow-clockwise"></i> تحقق من الحالة الآن
        </button>
    </div>
    <?php endif; ?>

    <?php endif; /* !$isActive */ ?>

    <div class="footer-small">
        © <?= date('Y') ?> <a href="https://almhdy24.com" target="_blank">Elmahdi Dev</a>
    </div>
</div>

<script>
const CSRF = <?= json_encode(Security::generateCSRFToken()) ?>;
const IS_WIZARD = <?= $isWizard ? 'true' : 'false' ?>;

function showMsg(msg, type = 'danger') {
    const box = document.getElementById('msgBox');
    if (!box) return;
    box.innerHTML = `<div class="alert alert-${type} mt-2">${msg}</div>`;
}

// ── Activate key ──────────────────────────────────────────────────────────────
const activateBtn = document.getElementById('activateBtn');
if (activateBtn) {
    activateBtn.addEventListener('click', async () => {
        const key = (document.getElementById('licKey')?.value ?? '').trim();
        if (!key) { showMsg('أدخل مفتاح الترخيص أولاً'); return; }

        activateBtn.disabled = true;
        activateBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> جارٍ التفعيل...';

        const fd = new FormData();
        fd.append('action', 'activate');
        fd.append('license_key', key);
        fd.append('csrf_token', CSRF);

        try {
            const r = await fetch('license.php', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) {
                showMsg('<i class="bi bi-check-circle-fill"></i> ' + d.msg, 'success');
                setTimeout(() => { location.href = IS_WIZARD ? 'license.php?wizard=1' : 'login.php'; }, 1800);
            } else {
                showMsg(d.msg);
                activateBtn.disabled = false;
                activateBtn.innerHTML = '<i class="bi bi-patch-check"></i> تفعيل الترخيص';
            }
        } catch (e) {
            showMsg('خطأ في الاتصال: ' + e.message);
            activateBtn.disabled = false;
            activateBtn.innerHTML = '<i class="bi bi-patch-check"></i> تفعيل الترخيص';
        }
    });
}

// ── Purchase flow ─────────────────────────────────────────────────────────────
// Load payment methods
async function loadMethods() {
    const sel = document.getElementById('payMethod');
    if (!sel) return;
    try {
        const fd = new FormData();
        fd.append('action', 'get_methods');
        fd.append('csrf_token', CSRF);
        const r = await fetch('license.php', { method: 'POST', body: fd });
        const d = await r.json();
        sel.innerHTML = '<option value="">اختر طريقة الدفع</option>';
        if (d.ok && d.methods.length) {
            d.methods.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id;
                opt.textContent = m.name + (m.currency ? ' (' + m.currency + ')' : '');
                opt.dataset.instructions = m.instructions ?? '';
                sel.appendChild(opt);
            });
        } else {
            sel.innerHTML = '<option value="">تعذر تحميل طرق الدفع (تحقق من الاتصال)</option>';
        }
    } catch (e) {
        if (sel) sel.innerHTML = '<option value="">تعذر الاتصال بالخادم</option>';
    }
}

const payMethod = document.getElementById('payMethod');
if (payMethod) {
    loadMethods();
    payMethod.addEventListener('change', () => {
        const opt = payMethod.options[payMethod.selectedIndex];
        const instr = document.getElementById('methodInstructions');
        if (instr) instr.textContent = opt?.dataset?.instructions ?? '';
    });
}

// Submit purchase
const purchaseBtn = document.getElementById('purchaseBtn');
if (purchaseBtn) {
    purchaseBtn.addEventListener('click', async () => {
        const name    = document.getElementById('buyerName')?.value.trim()    ?? '';
        const contact = document.getElementById('buyerContact')?.value.trim() ?? '';
        const method  = document.getElementById('payMethod')?.value           ?? '';
        const proof   = document.getElementById('proofFile')?.files[0];
        const note    = document.getElementById('proofNote')?.value.trim()    ?? '';

        if (!name || !contact || !method || !proof) {
            showMsg('يرجى ملء جميع الحقول المطلوبة وإرفاق صورة الإثبات');
            return;
        }

        purchaseBtn.disabled = true;
        purchaseBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> جارٍ الإرسال...';

        const fd = new FormData();
        fd.append('action',            'create_order');
        fd.append('csrf_token',        CSRF);
        fd.append('buyer_name',        name);
        fd.append('buyer_contact',     contact);
        fd.append('payment_method_id', method);
        fd.append('proof_file',        proof);
        fd.append('proof_note',        note);

        try {
            const r = await fetch('license.php', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.ok) {
                document.getElementById('purchaseForm').style.display = 'none';
                document.getElementById('orderStatus').style.display  = '';
                document.getElementById('orderRef').textContent = d.reference;
                startPolling(d.reference);
            } else {
                showMsg(d.msg);
                purchaseBtn.disabled = false;
                purchaseBtn.innerHTML = '<i class="bi bi-send-fill"></i> إرسال طلب الشراء';
            }
        } catch (e) {
            showMsg('خطأ: ' + e.message);
            purchaseBtn.disabled = false;
            purchaseBtn.innerHTML = '<i class="bi bi-send-fill"></i> إرسال طلب الشراء';
        }
    });
}

// Polling
let pollInterval = null;
function startPolling(ref) {
    document.getElementById('statusSpinner').style.display = 'inline-block';
    pollInterval = setInterval(() => checkOrder(ref), 10000);
    checkOrder(ref);
}

async function checkOrder(ref) {
    const fd = new FormData();
    fd.append('action',     'check_order');
    fd.append('csrf_token', CSRF);
    fd.append('reference',  ref);

    try {
        const r = await fetch('license.php', { method: 'POST', body: fd });
        const d = await r.json();
        const textEl = document.getElementById('statusText');

        const labels = { pending: 'في انتظار المراجعة...', approved: 'تمت الموافقة!', rejected: 'مرفوض' };
        if (textEl) textEl.textContent = labels[d.status] ?? d.status;

        if (d.status === 'approved' && d.activated) {
            clearInterval(pollInterval);
            showMsg('<i class="bi bi-check-circle-fill"></i> تم تفعيل الترخيص! جارٍ الدخول...', 'success');
            setTimeout(() => { location.href = 'login.php'; }, 2000);
        } else if (d.status === 'rejected') {
            clearInterval(pollInterval);
            document.getElementById('statusSpinner').style.display = 'none';
            showMsg('تم رفض الطلب.' + (d.admin_note ? ' ملاحظة: ' + d.admin_note : ''));
        }
    } catch (e) { /* silent: offline is expected */ }
}

const checkStatusBtn = document.getElementById('checkStatusBtn');
if (checkStatusBtn) {
    checkStatusBtn.addEventListener('click', () => {
        const ref = document.getElementById('orderRef')?.textContent ?? '';
        if (ref) checkOrder(ref);
    });
}

// Restore order status if already have a reference in session
<?php if (!empty($_SESSION['purchase_reference'])): ?>
document.addEventListener('DOMContentLoaded', () => {
    const ref = <?= json_encode($_SESSION['purchase_reference']) ?>;
    if (ref) {
        const pf = document.getElementById('purchaseForm');
        const os = document.getElementById('orderStatus');
        const or_ = document.getElementById('orderRef');
        if (pf) pf.style.display = 'none';
        if (os) os.style.display = '';
        if (or_) or_.textContent = ref;
        startPolling(ref);
    }
});
<?php endif; ?>
</script>

<script src="/assets/bootstrap.bundle.min.js"></script>
</body>
</html>
