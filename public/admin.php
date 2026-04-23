<?php
require_once __DIR__.'/../app/helpers.php';
use Middleware\AuthMiddleware;
use Models\Item;
use Models\Category;
use Models\Shift;
use Models\Order;
use Models\OrderItem;
use Core\Auth;
use Core\Security;

AuthMiddleware::handle('manage_items');

$user = Auth::user();
$shift_id = Shift::getActiveOrOpen($user['id']);
$stats = Order::getShiftStats($shift_id);
$best = OrderItem::bestSellers($shift_id);
$items = Item::all();
$categories = Category::all();
$csrf = Security::generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'])) die('Invalid CSRF');
    if (isset($_POST['add_item'])) {
        $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
        Item::add($_POST['name'], $_POST['price'], $category_id);
    } elseif (isset($_POST['update_item'])) {
        $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
        Item::update($_POST['id'], $_POST['name'], $_POST['price'], $category_id);
    } elseif (isset($_POST['delete_item'])) {
        Item::delete($_POST['id']);
    }
    header('Location: admin.php');
    exit;
}

$pageTitle = 'لوحة التحكم - Cashirak V2';
include __DIR__.'/../views/partials/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-speedometer2"></i> لوحة التحكم</h2>
        <div>
            <a href="index.php" class="btn btn-outline-primary"><i class="bi bi-cart"></i> الكاشير</a>
            <a href="admin/categories.php" class="btn btn-outline-info me-2"><i class="bi bi-tags"></i> التصنيفات</a>
            <a href="shift-close.php" class="btn btn-warning"><i class="bi bi-door-closed"></i> إغلاق الوردية</a>
            <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> خروج</a>
        </div>
    </div>
    
    <!-- بطاقات الإحصائيات -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5><i class="bi bi-clock"></i> وردية #<?= $shift_id ?></h5>
                    <p class="mb-0">نشطة</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5><i class="bi bi-receipt"></i> الطلبات</h5>
                    <h2><?= $stats['orders'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5><i class="bi bi-cash-stack"></i> المبيعات</h5>
                    <h2><?= number_format($stats['sales'] ?? 0) ?> SDG</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-secondary">
                <div class="card-body">
                    <h5><i class="bi bi-trophy"></i> متوسط الطلب</h5>
                    <h2><?= $stats['orders'] ? round(($stats['sales']??0)/$stats['orders']) : 0 ?> SDG</h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- الأكثر مبيعاً -->
        <div class="col-md-5">
            <div class="card p-3">
                <h5><i class="bi bi-star-fill text-warning"></i> الأكثر مبيعاً اليوم</h5>
                <?php if (!empty($best)): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach($best as $b): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <?= htmlspecialchars($b['name']) ?>
                        <span class="badge bg-primary rounded-pill"><?= $b['sold'] ?> قطعة</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <p class="text-muted">لا توجد مبيعات بعد</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- إدارة الأصناف -->
        <div class="col-md-7">
            <div class="card p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5><i class="bi bi-pencil-square"></i> إدارة الأصناف</h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
                        <i class="bi bi-plus-circle"></i> إضافة صنف
                    </button>
                </div>
                
                <!-- جدول الأصناف -->
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>الصنف</th>
                            <th>التصنيف</th>
                            <th>السعر</th>
                            <th width="100">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td>
                                <?php if($item['category_name']): ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($item['category_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">بدون تصنيف</span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($item['price']) ?> SDG</td>
                            <td>
                                <button class="btn btn-sm btn-outline-warning" onclick="editItem(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name']) ?>', <?= $item['price'] ?>, <?= $item['category_id'] ?? 'null' ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="post" style="display:inline;" onsubmit="return confirm('حذف نهائي؟')">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button type="submit" name="delete_item" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal إضافة صنف -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="modal-header">
                <h5><i class="bi bi-plus-circle"></i> إضافة صنف جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">اسم الصنف</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">السعر (SDG)</label>
                    <input type="number" name="price" class="form-control" step="1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">التصنيف</label>
                    <select name="category_id" class="form-select">
                        <option value="">بدون تصنيف</option>
                        <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" name="add_item" class="btn btn-success">إضافة</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal تعديل صنف -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal-header">
                <h5><i class="bi bi-pencil"></i> تعديل الصنف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">اسم الصنف</label>
                    <input type="text" name="name" id="edit-name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">السعر (SDG)</label>
                    <input type="number" name="price" id="edit-price" class="form-control" step="1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">التصنيف</label>
                    <select name="category_id" id="edit-category" class="form-select">
                        <option value="">بدون تصنيف</option>
                        <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" name="update_item" class="btn btn-warning">تحديث</button>
            </div>
        </form>
    </div>
</div>

<script>
function editItem(id, name, price, categoryId) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-name').value = name;
    document.getElementById('edit-price').value = price;
    document.getElementById('edit-category').value = categoryId || '';
    new bootstrap.Modal(document.getElementById('editItemModal')).show();
}
</script>

<?php include __DIR__.'/../views/partials/footer.php'; ?>
