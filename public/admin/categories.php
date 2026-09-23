<?php
require_once __DIR__ . '/../../bootstrap.php';
use Middleware\AuthMiddleware;
use Models\Category;
use Core\Security;

AuthMiddleware::handle('manage_items');

$categories = Category::all();
$csrf = Security::generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'])) die('Invalid CSRF');
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        Category::create($_POST['name'], $_POST['description']);
    } elseif ($action === 'update') {
        Category::update($_POST['id'], $_POST['name'], $_POST['description']);
    } elseif ($action === 'delete') {
        Category::delete($_POST['id']);
    }
    header('Location: categories.php');
    exit;
}

$pageTitle = getSetting('cafe_title', 'كاشيراك') . ' - إدارة التصنيفات';
include __DIR__.'/../../views/partials/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-tags"></i> إدارة التصنيفات</h2>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="bi bi-plus-circle"></i> إضافة تصنيف
    </button>
</div>

<div class="card" style="max-width:700px;">
    <div class="card-body p-0 table-responsive">
        <?php if (empty($categories)): ?>
        <div class="empty-state">
            <i class="bi bi-tags"></i>
            <p>لا توجد تصنيفات — أضف تصنيفاً للبدء</p>
        </div>
        <?php else: ?>
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>الاسم</th>
                    <th>الوصف</th>
                    <th class="text-center">الأصناف</th>
                    <th width="110">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($cat['name']) ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($cat['description'] ?? '—') ?></td>
                    <td class="text-center">
                        <span class="badge bg-secondary rounded-pill"><?= Category::getItemsCount($cat['id']) ?></span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-warning edit-cat-btn"
                                data-id="<?= $cat['id'] ?>"
                                data-name="<?= htmlspecialchars($cat['name']) ?>"
                                data-desc="<?= htmlspecialchars($cat['description'] ?? '') ?>"
                                title="تعديل">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="post" style="display:inline;"
                              data-confirm="حذف تصنيف «<?= htmlspecialchars($cat['name']) ?>»؟ الأصناف المرتبطة ستصبح بدون تصنيف.">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Add modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> إضافة تصنيف جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">الاسم <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">الوصف (اختياري)</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-success">حفظ</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> تعديل التصنيف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">الاسم <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="edit-name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">الوصف</label>
                    <textarea name="description" id="edit-description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-warning"><i class="bi bi-save"></i> تحديث</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__.'/../../views/partials/footer.php'; ?>

<script>
document.querySelectorAll('.edit-cat-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit-id').value          = btn.dataset.id;
        document.getElementById('edit-name').value        = btn.dataset.name;
        document.getElementById('edit-description').value = btn.dataset.desc;
        new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
    });
});
</script>
