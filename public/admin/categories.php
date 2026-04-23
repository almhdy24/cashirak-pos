<?php
require_once __DIR__.'/../../app/helpers.php';
use Middleware\AuthMiddleware;
use Models\Category;
use Core\Auth;
use Core\Security;

AuthMiddleware::handle('manage_items');

$user = Auth::user();
$categories = Category::all();
$csrf = Security::generateCSRFToken();

$pageTitle = 'إدارة التصنيفات - Cashirak V2';
include __DIR__.'/../../views/partials/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-tags"></i> إدارة التصنيفات</h2>
        <div>
            <a href="../admin.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> العودة للوحة التحكم</a>
            <a href="../logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> خروج</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5>قائمة التصنيفات</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="bi bi-plus-circle"></i> إضافة تصنيف
            </button>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>الوصف</th>
                        <th>عدد الأصناف</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($categories as $cat): ?>
                    <tr>
                        <td><?= $cat['id'] ?></td>
                        <td><?= htmlspecialchars($cat['name']) ?></td>
                        <td><?= htmlspecialchars($cat['description'] ?? '') ?></td>
                        <td><?= Category::getItemsCount($cat['id']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-warning" onclick="editCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name']) ?>', '<?= htmlspecialchars($cat['description'] ?? '') ?>')">
                                <i class="bi bi-pencil"></i> تعديل
                            </button>
                            <form method="post" style="display:inline;" onsubmit="return confirm('حذف التصنيف؟ الأصناف المرتبطة ستصبح بدون تصنيف.')">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> حذف</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal إضافة تصنيف -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h5>إضافة تصنيف جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>الاسم</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>الوصف (اختياري)</label>
                    <textarea name="description" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-success">حفظ</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal تعديل -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal-header">
                <h5>تعديل التصنيف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>الاسم</label>
                    <input type="text" name="name" id="edit-name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>الوصف</label>
                    <textarea name="description" id="edit-description" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-warning">تحديث</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCategory(id, name, desc) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-description').value = desc;
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}
</script>

<?php
// معالجة النماذج
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
include __DIR__.'/../../views/partials/footer.php';
