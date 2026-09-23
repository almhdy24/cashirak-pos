<?php
require_once __DIR__ . '/../../bootstrap.php';
use Middleware\AuthMiddleware;
use Core\Auth;
use Core\Security;

AuthMiddleware::handle('manage_items');

$user = Auth::user();
if ($user['role'] !== 'admin') {
    header('Location: ../admin.php');
    exit;
}

$DB_PATH = defined('DB_PATH') ? DB_PATH : ROOT_PATH . '/database/cashirak.sqlite';
$DB_DIR  = dirname($DB_PATH);
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) die('CSRF غير صحيح');

    $action = $_POST['action'] ?? '';

    if ($action === 'backup') {
        if (!file_exists($DB_PATH)) die('ملف قاعدة البيانات غير موجود');
        $filename = 'cashirak-backup-' . date('Y-m-d_H-i-s') . '.sqlite';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($DB_PATH));
        header('Cache-Control: no-cache, no-store');
        readfile($DB_PATH);
        exit;
    }

    if ($action === 'restore') {
        $file = $_FILES['db_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = 'فشل رفع الملف. تأكد من اختيار ملف صحيح.';
        } elseif ($file['size'] < 100) {
            $error = 'الملف صغير جداً — ليس ملف قاعدة بيانات صحيحاً.';
        } else {
            $fh    = fopen($file['tmp_name'], 'rb');
            $magic = fread($fh, 16);
            fclose($fh);
            if (strncmp($magic, 'SQLite format 3', 15) !== 0) {
                $error = 'الملف المرفوع ليس قاعدة بيانات SQLite صحيحة.';
            } else {
                $safetyName = $DB_DIR . '/cashirak-before-restore-' . date('Y-m-d_H-i-s') . '.sqlite';
                if (file_exists($DB_PATH) && !copy($DB_PATH, $safetyName)) {
                    $error = 'فشل إنشاء نسخة الأمان. الاستعادة ملغاة.';
                } elseif (!copy($file['tmp_name'], $DB_PATH)) {
                    $error = 'فشل استبدال قاعدة البيانات.';
                    if (file_exists($safetyName)) copy($safetyName, $DB_PATH);
                } else {
                    $success = 'تمت الاستعادة بنجاح. تأكد من تسجيل الدخول من جديد.';
                }
            }
        }
    }
}

$safetyBackups = [];
if (is_dir($DB_DIR)) {
    foreach (glob($DB_DIR . '/cashirak-before-restore-*.sqlite') as $f) {
        $safetyBackups[] = ['name' => basename($f), 'size' => round(filesize($f) / 1024, 1), 'time' => filemtime($f)];
    }
    usort($safetyBackups, fn($a, $b) => $b['time'] - $a['time']);
}

$csrf      = Security::generateCSRFToken();
$dbSize    = file_exists($DB_PATH) ? round(filesize($DB_PATH) / 1024, 1) : 0;
$pageTitle = getSetting('cafe_title', 'كاشيراك') . ' - النسخ الاحتياطي';
include __DIR__ . '/../../views/partials/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-archive"></i> النسخ الاحتياطي والاستعادة</h2>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-4" style="max-width:860px;">

    <!-- Backup -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header" style="background:#f0fdf4; border-bottom-color:#d1fae5;">
                <h5 class="mb-0" style="color:#15803d;"><i class="bi bi-download"></i> تنزيل نسخة احتياطية</h5>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                <p class="text-muted mb-0">تحميل قاعدة البيانات الكاملة كملف احتياطي.</p>
                <div class="alert alert-info py-2 small mb-0">
                    <i class="bi bi-database"></i>
                    حجم قاعدة البيانات الحالية: <strong><?= $dbSize ?> KB</strong>
                </div>
                <p class="text-muted small mb-0">احفظ الملف في مكان آمن خارج الجهاز (USB، بريد، إلخ).</p>
                <form method="POST" class="mt-auto">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-cloud-download"></i> تنزيل قاعدة البيانات
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Restore -->
    <div class="col-md-6">
        <div class="card h-100 border-warning">
            <div class="card-header" style="background:#fffbeb; border-bottom-color:#fde68a;">
                <h5 class="mb-0" style="color:#92400e;"><i class="bi bi-upload"></i> استعادة من نسخة احتياطية</h5>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                <div class="alert alert-warning py-2 small mb-0">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>تحذير:</strong> ستُستبدل قاعدة البيانات الحالية كاملاً.
                    يتم حفظ نسخة أمان تلقائياً قبل الاستبدال.
                </div>
                <p class="text-muted small mb-0">ارفع ملف <code>.sqlite</code> تم تنزيله من هذه الصفحة فقط.</p>
                <form method="POST" enctype="multipart/form-data" class="mt-auto"
                      data-confirm="تأكيد استعادة قاعدة البيانات؟ ستُفقد جميع البيانات الحالية وتُستبدل بالنسخة المرفوعة.">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="restore">
                    <div class="mb-3">
                        <input type="file" name="db_file" class="form-control" accept=".sqlite" required>
                    </div>
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="bi bi-arrow-counterclockwise"></i> استعادة قاعدة البيانات
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($safetyBackups)): ?>
<div class="card mt-4" style="max-width:860px;">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-shield-check"></i> نسخ الأمان التلقائية (<?= count($safetyBackups) ?>)</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>اسم الملف</th><th>الحجم</th><th>التاريخ</th></tr>
            </thead>
            <tbody>
                <?php foreach ($safetyBackups as $bk): ?>
                <tr>
                    <td><code style="font-size:.8rem;"><?= htmlspecialchars($bk['name']) ?></code></td>
                    <td class="text-muted small"><?= $bk['size'] ?> KB</td>
                    <td class="text-muted small"><?= date('Y-m-d H:i', $bk['time']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-muted small">
        <i class="bi bi-info-circle"></i>
        هذه الملفات موجودة في مجلد <code>database/</code> على الخادم. احذفها يدوياً عند الحاجة.
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
