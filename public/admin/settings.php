<?php
require_once __DIR__ . '/../../bootstrap.php';
use Middleware\AuthMiddleware;
use Core\Security;
use Core\Settings;
use Core\License;

AuthMiddleware::handle('manage_items');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) die('CSRF غير صحيح');

    Settings::set('cafe_title',     trim($_POST['cafe_title']     ?? ''));
    Settings::set('invoice_footer', trim($_POST['invoice_footer'] ?? ''));
    Settings::set('currency',       trim($_POST['currency']       ?? ''));

    // Validate date format before saving
    $fmt = trim($_POST['date_format'] ?? 'Y-m-d H:i:s');
    try { date($fmt); Settings::set('date_format', $fmt); }
    catch (\Throwable $e) { /* ignore bad format */ }

    $message = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> تم حفظ الإعدادات بنجاح!</div>';
}

$current    = Settings::getAll();
$csrf       = Security::generateCSRFToken();
$licInfo    = License::getInfo();
$deviceId   = License::deviceId();
$pageTitle  = getSetting('cafe_title', 'كاشيراك') . ' - الإعدادات';
include __DIR__ . '/../../views/partials/header.php';
?>

<div class="container" style="max-width:700px;">
    <div class="page-header">
        <h2><i class="bi bi-gear"></i> إعدادات النظام</h2>
    </div>

    <?= $message ?>

    <!-- General settings -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white"><h5 class="mb-0">إعدادات المحل والفاتورة</h5></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <div class="mb-3">
                    <label class="form-label fw-semibold"><i class="bi bi-shop"></i> اسم المحل / المقهى</label>
                    <input type="text" name="cafe_title" class="form-control"
                           value="<?= htmlspecialchars($current['cafe_title'] ?? 'كاشيراك') ?>" required>
                    <div class="form-text">يظهر في عنوان الصفحة والفاتورة</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold"><i class="bi bi-chat-text"></i> تذييل الفاتورة</label>
                    <textarea name="invoice_footer" class="form-control" rows="2"><?= htmlspecialchars($current['invoice_footer'] ?? 'شكراً لزيارتكم') ?></textarea>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col">
                        <label class="form-label fw-semibold"><i class="bi bi-calendar"></i> تنسيق التاريخ</label>
                        <input type="text" name="date_format" class="form-control"
                               value="<?= htmlspecialchars($current['date_format'] ?? 'Y-m-d H:i:s') ?>">
                        <div class="form-text">مثال: Y-m-d H:i:s &nbsp;|&nbsp; d/m/Y h:i A</div>
                    </div>
                    <div class="col">
                        <label class="form-label fw-semibold"><i class="bi bi-currency-dollar"></i> رمز العملة</label>
                        <input type="text" name="currency" class="form-control"
                               value="<?= htmlspecialchars($current['currency'] ?? 'SDG') ?>" maxlength="10">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> حفظ الإعدادات
                </button>
            </form>
        </div>
    </div>

    <!-- Payment methods shortcut -->
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-credit-card"></i> طرق الدفع</h5>
            <a href="payment-methods.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil-square"></i> إدارة طرق الدفع
            </a>
        </div>
        <div class="card-body p-3">
            <small class="text-muted">أضف أو عدّل طرق الدفع المتاحة في شاشة الكاشير (كاش، بنكك، ماي كاشي...)</small>
        </div>
    </div>

    <!-- License info -->
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-patch-check"></i> معلومات الترخيص</h5>
            <?php if (($licInfo['status'] ?? '') !== 'active'): ?>
            <a href="../license.php" class="btn btn-warning btn-sm">تفعيل الترخيص</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <table class="table table-borderless table-sm mb-0">
                <tbody>
                    <tr>
                        <td class="text-muted">الحالة</td>
                        <td>
                            <?php if (($licInfo['status'] ?? '') === 'active'): ?>
                            <span class="badge bg-success"><i class="bi bi-patch-check-fill"></i> نشط</span>
                            <?php else: ?>
                            <span class="badge bg-danger">غير مفعّل</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="white-space:nowrap;">مفتاح الترخيص</td>
                        <td><code style="word-break:break-all;font-size:.8rem;"><?= htmlspecialchars($licInfo['license_key'] ?? '—') ?></code></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="white-space:nowrap;">معرّف الجهاز</td>
                        <td><code style="word-break:break-all;font-size:.75rem;"><?= htmlspecialchars($deviceId) ?></code></td>
                    </tr>
                    <?php if (!empty($licInfo['activated_at'])): ?>
                    <tr>
                        <td class="text-muted">تاريخ التفعيل</td>
                        <td><?= date('Y-m-d H:i', $licInfo['activated_at']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($licInfo['last_seen_time'])): ?>
                    <tr>
                        <td class="text-muted">آخر تشغيل</td>
                        <td><?= date('Y-m-d H:i', $licInfo['last_seen_time']) ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="alert alert-info small">
        <i class="bi bi-plug-fill"></i> كاشيراك يعمل بدون إنترنت — جميع البيانات محفوظة محلياً على هذا الجهاز.
    </div>
</div>

<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
