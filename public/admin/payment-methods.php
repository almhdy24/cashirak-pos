<?php
require_once __DIR__ . '/../../bootstrap.php';
use Middleware\AuthMiddleware;
use Core\Security;
use Core\DB;

AuthMiddleware::handle('manage_items');

$csrf = Security::generateCSRFToken();
$msg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) die('CSRF غير صحيح');
    $db     = DB::conn();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $max = (int)($db->query("SELECT MAX(sort_order) FROM payment_methods")->fetchColumn() ?? 0);
            $db->prepare("INSERT INTO payment_methods (name, is_active, sort_order) VALUES (?, 1, ?)")
               ->execute([$name, $max + 1]);
            $msg = 'تمت الإضافة';
        }
    } elseif ($action === 'toggle') {
        $db->prepare("UPDATE payment_methods SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id = ?")
           ->execute([(int)$_POST['id']]);
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM payment_methods WHERE id = ?")->execute([(int)$_POST['id']]);
        $msg = 'تم الحذف';
    } elseif ($action === 'rename') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $db->prepare("UPDATE payment_methods SET name = ? WHERE id = ?")->execute([$name, (int)$_POST['id']]);
            $msg = 'تم التحديث';
        }
    }

    header('Location: payment-methods.php' . ($msg ? '?ok=1' : ''));
    exit;
}

if (isset($_GET['ok'])) $msg = 'تمت العملية بنجاح';

$methods   = DB::conn()->query("SELECT * FROM payment_methods ORDER BY sort_order, id")->fetchAll(\PDO::FETCH_ASSOC);
$pageTitle = getSetting('cafe_title', 'كاشيراك') . ' - طرق الدفع';
include __DIR__ . '/../../views/partials/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-credit-card-2-back"></i> طرق الدفع</h2>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle"></i> إضافة طريقة
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card mb-4" style="max-width:600px;">
    <div class="card-body p-0">
        <?php if (empty($methods)): ?>
        <div class="empty-state">
            <i class="bi bi-credit-card"></i>
            <p>لا توجد طرق دفع — أضف طريقة للبدء</p>
        </div>
        <?php else: ?>
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr><th>الاسم</th><th class="text-center">الحالة</th><th width="130">إجراءات</th></tr>
            </thead>
            <tbody>
                <?php foreach ($methods as $m): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($m['name']) ?></td>
                    <td class="text-center">
                        <span class="badge <?= $m['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $m['is_active'] ? 'نشط' : 'معطل' ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-warning"
                                    onclick="openRename(<?= $m['id'] ?>, <?= json_encode($m['name']) ?>)"
                                    title="تعديل">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $m['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                                        title="<?= $m['is_active'] ? 'تعطيل' : 'تفعيل' ?>">
                                    <i class="bi <?= $m['is_active'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                                </button>
                            </form>
                            <form method="post"
                                  data-confirm="حذف طريقة الدفع «<?= htmlspecialchars($m['name']) ?>» نهائياً؟">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div class="alert alert-info small" style="max-width:600px;">
    <i class="bi bi-info-circle"></i>
    طرق الدفع المفعّلة هنا ستظهر كأزرار في شاشة الكاشير.
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form method="post" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> إضافة طريقة دفع</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" name="name" class="form-control" placeholder="مثال: بنكك، ماي كاشي، كاش..." required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-success">إضافة</button>
            </div>
        </form>
    </div>
</div>

<!-- Rename Modal -->
<div class="modal fade" id="renameModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form method="post" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="id" id="rename-id">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> تعديل الاسم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" name="name" id="rename-name" class="form-control" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-warning">تحديث</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../views/partials/footer.php'; ?>

<script>
function openRename(id, name) {
    document.getElementById('rename-id').value   = id;
    document.getElementById('rename-name').value = name;
    new bootstrap.Modal(document.getElementById('renameModal')).show();
}
</script>
