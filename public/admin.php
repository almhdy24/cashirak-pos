<?php
require_once __DIR__ . '/../bootstrap.php';
use Middleware\AuthMiddleware;
use Models\Item;
use Models\Category;
use Models\Shift;
use Models\Order;
use Models\OrderItem;
use Core\Auth;
use Core\Security;

AuthMiddleware::handle('manage_items');

$user     = Auth::user();
$shift_id = Shift::getActiveOrOpen($user['id']);
$stats    = Order::getShiftStats($shift_id);
$best     = OrderItem::bestSellers($shift_id);
$items    = Item::all();
$cats     = Category::all();
$csrf     = Security::generateCSRFToken();
$currency = getSetting('currency', 'SDG');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) die('CSRF غير صحيح');

    $barcode    = trim($_POST['barcode']    ?? '');
    $cat_id     = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $stock      = $_POST['stock'] !== '' ? (int)$_POST['stock'] : null;
    $cost_price = (float)($_POST['cost_price'] ?? 0);

    if (isset($_POST['add_item'])) {
        $ok = Item::add(
            trim($_POST['name']  ?? ''),
            (float)($_POST['price'] ?? 0),
            $cat_id, $barcode, $stock, $cost_price
        );
        $success = $ok ? 'تم إضافة الصنف بنجاح' : 'فشل الإضافة (ربما الاسم مكرر)';
    } elseif (isset($_POST['update_item'])) {
        Item::update(
            (int)$_POST['id'],
            trim($_POST['name']  ?? ''),
            (float)($_POST['price'] ?? 0),
            $cat_id, $barcode, $stock, $cost_price
        );
        $success = 'تم تحديث الصنف';
    } elseif (isset($_POST['delete_item'])) {
        Item::delete((int)$_POST['id']);
        $success = 'تم حذف الصنف';
    }

    header('Location: admin.php' . ($success ? '?ok=1' : '?err=1'));
    exit;
}

if (isset($_GET['ok']))  $success = 'تمت العملية بنجاح';
if (isset($_GET['err'])) $error   = 'فشل في تنفيذ العملية';

$pageTitle = getSetting('cafe_title', 'كاشيراك') . ' - لوحة التحكم';
include __DIR__ . '/../views/partials/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-speedometer2"></i> لوحة التحكم</h2>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Stat cards -->
<div class="row mb-4 g-3">
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-clock-history"></i></div>
            <div>
                <div class="stat-label">الوردية الحالية</div>
                <div class="stat-value">#<?= $shift_id ?></div>
                <div class="stat-sub">نشطة</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-receipt"></i></div>
            <div>
                <div class="stat-label">الطلبات</div>
                <div class="stat-value"><?= number_format($stats['orders'] ?? 0) ?></div>
                <div class="stat-sub">هذه الوردية</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="stat-label">المبيعات</div>
                <div class="stat-value"><?= number_format($stats['sales'] ?? 0) ?></div>
                <div class="stat-sub"><?= htmlspecialchars($currency) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon slate"><i class="bi bi-graph-up"></i></div>
            <div>
                <div class="stat-label">متوسط الطلب</div>
                <div class="stat-value"><?= $stats['orders'] ? number_format(($stats['sales'] ?? 0) / $stats['orders'], 0) : 0 ?></div>
                <div class="stat-sub"><?= htmlspecialchars($currency) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Best sellers -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-star-fill text-warning"></i> الأكثر مبيعاً</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($best)): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($best as $b): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= htmlspecialchars($b['name']) ?>
                        <span class="badge bg-primary rounded-pill"><?= $b['sold'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="empty-state" style="padding:2rem 1rem;">
                    <i class="bi bi-bar-chart" style="font-size:1.8rem;"></i>
                    <p>لا توجد مبيعات بعد</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Items table -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-box-seam"></i> الأصناف (<?= count($items) ?>)</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="bi bi-plus-circle"></i> إضافة صنف
                </button>
            </div>
            <div class="card-body p-0 table-responsive">
                <?php if (empty($items)): ?>
                <div class="empty-state">
                    <i class="bi bi-box"></i>
                    <p>لا توجد أصناف — أضف صنفاً جديداً للبدء</p>
                </div>
                <?php else: ?>
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>الصنف</th>
                            <th>الباركود</th>
                            <th>التصنيف</th>
                            <th>السعر</th>
                            <th>المخزون</th>
                            <th width="90">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($item['name']) ?></td>
                            <td>
                                <?php if ($item['barcode']): ?>
                                <code class="small"><?= htmlspecialchars($item['barcode']) ?></code>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $item['category_name']
                                ? '<span class="badge bg-secondary">'.htmlspecialchars($item['category_name']).'</span>'
                                : '<span class="text-muted small">—</span>' ?></td>
                            <td><?= number_format($item['price']) ?> <span class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars($currency) ?></span></td>
                            <td>
                                <?php if ($item['stock'] === null): ?>
                                <span class="text-muted small">∞</span>
                                <?php elseif ($item['stock'] <= 0): ?>
                                <span class="badge bg-danger">نفد</span>
                                <?php elseif ($item['stock'] <= 5): ?>
                                <span class="badge bg-warning text-dark"><?= $item['stock'] ?></span>
                                <?php else: ?>
                                <span class="badge bg-success"><?= $item['stock'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-warning edit-item-btn"
                                        data-id="<?= $item['id'] ?>"
                                        data-name="<?= htmlspecialchars($item['name']) ?>"
                                        data-price="<?= (int)$item['price'] ?>"
                                        data-category="<?= (int)($item['category_id'] ?? 0) ?>"
                                        data-barcode="<?= htmlspecialchars($item['barcode'] ?? '') ?>"
                                        data-stock="<?= $item['stock'] !== null ? (int)$item['stock'] : '' ?>"
                                        data-cost="<?= (float)($item['cost_price'] ?? 0) ?>"
                                        title="تعديل">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="post" style="display:inline;"
                                      data-confirm="حذف «<?= htmlspecialchars($item['name']) ?>» نهائياً؟">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button type="submit" name="delete_item" class="btn btn-sm btn-outline-danger" title="حذف">
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
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> إضافة صنف جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">اسم الصنف <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col">
                        <label class="form-label">السعر (<?= htmlspecialchars($currency) ?>) <span class="text-danger">*</span></label>
                        <input type="number" name="price" class="form-control" step="1" min="0" required>
                    </div>
                    <div class="col">
                        <label class="form-label">سعر التكلفة</label>
                        <input type="number" name="cost_price" class="form-control" step="1" min="0" value="0">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-upc-scan"></i> الباركود</label>
                    <input type="text" name="barcode" class="form-control font-monospace"
                           dir="ltr" placeholder="اترك فارغاً إذا لم يكن مزوداً بباركود">
                </div>
                <div class="row g-3">
                    <div class="col">
                        <label class="form-label">المخزون الأولي</label>
                        <input type="number" name="stock" class="form-control" min="0"
                               placeholder="فارغ = غير محدود">
                    </div>
                    <div class="col">
                        <label class="form-label">التصنيف</label>
                        <select name="category_id" class="form-select">
                            <option value="">بدون تصنيف</option>
                            <?php foreach ($cats as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" name="add_item" class="btn btn-success"><i class="bi bi-plus-circle"></i> إضافة</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="id" id="edit-id">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> تعديل الصنف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">اسم الصنف <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="edit-name" class="form-control" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col">
                        <label class="form-label">السعر (<?= htmlspecialchars($currency) ?>) <span class="text-danger">*</span></label>
                        <input type="number" name="price" id="edit-price" class="form-control" step="1" min="0" required>
                    </div>
                    <div class="col">
                        <label class="form-label">سعر التكلفة</label>
                        <input type="number" name="cost_price" id="edit-cost" class="form-control" step="1" min="0">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-upc-scan"></i> الباركود</label>
                    <input type="text" name="barcode" id="edit-barcode" class="form-control font-monospace" dir="ltr">
                </div>
                <div class="row g-3">
                    <div class="col">
                        <label class="form-label">المخزون</label>
                        <input type="number" name="stock" id="edit-stock" class="form-control" min="0"
                               placeholder="فارغ = غير محدود">
                    </div>
                    <div class="col">
                        <label class="form-label">التصنيف</label>
                        <select name="category_id" id="edit-category" class="form-select">
                            <option value="">بدون تصنيف</option>
                            <?php foreach ($cats as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" name="update_item" class="btn btn-warning"><i class="bi bi-save"></i> تحديث</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>

<script>
document.querySelectorAll('.edit-item-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit-id').value       = btn.dataset.id;
        document.getElementById('edit-name').value     = btn.dataset.name;
        document.getElementById('edit-price').value    = btn.dataset.price;
        document.getElementById('edit-cost').value     = btn.dataset.cost || 0;
        document.getElementById('edit-barcode').value  = btn.dataset.barcode || '';
        document.getElementById('edit-stock').value    = btn.dataset.stock !== '' ? btn.dataset.stock : '';
        document.getElementById('edit-category').value = btn.dataset.category || '';
        new bootstrap.Modal(document.getElementById('editItemModal')).show();
    });
});
</script>
