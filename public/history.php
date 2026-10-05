<?php
require_once __DIR__ . '/../bootstrap.php';
use Middleware\AuthMiddleware;
use Core\Auth;
use Core\DB;
use Core\Security;
use Models\Shift;
use Services\OrderService;

AuthMiddleware::handle('process_order');

$user = Auth::user();
$shift_id = Shift::getActiveOrOpen($user['id']);
$db = DB::conn();

$stmt = $db->prepare("
    SELECT o.*,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    WHERE o.shift_id = ? AND o.status = 'active'
    ORDER BY o.created_at DESC
");
$stmt->execute([$shift_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
$canCancel = Auth::hasPermission('manage_items');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    if (!$canCancel) {
        http_response_code(403);
        die('إلغاء الطلبات متاح للمدير فقط');
    }
    $order_id = (int)$_POST['order_id'];
    try {
        OrderService::cancelOrder($order_id, $user['id']);
        header("Location: history.php?msg=cancelled");
        exit;
    } catch (\Exception $e) {
        $message = '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> خطأ: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'cancelled') {
    $message = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> تم إلغاء الطلب بنجاح</div>';
}

$csrf     = Security::generateCSRFToken();
$currency = getSetting('currency', 'SDG');
$pageTitle = getSetting('cafe_title', 'كاشيراك') . ' - سجل الفواتير';
include __DIR__.'/../views/partials/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-clock-history"></i> فواتير الوردية #<?= $shift_id ?></h2>
    <span class="badge bg-primary rounded-pill px-3 py-2" style="font-size:.82rem;">
        <?= count($orders) ?> طلب
    </span>
</div>

<?= $message ?>

<div class="card">
    <div class="card-header bg-white d-flex align-items-center gap-2">
        <h5 class="mb-0"><i class="bi bi-receipt"></i> الطلبات النشطة</h5>
        <span class="badge bg-secondary rounded-pill ms-auto"><?= count($orders) ?></span>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>رقم الفاتورة</th>
                    <th>الوقت</th>
                    <th>الأصناف</th>
                    <th>الإجمالي</th>
                    <th>طريقة الدفع</th>
                    <th width="140">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="bi bi-receipt"></i>
                            <p>لا توجد طلبات في هذه الوردية حتى الآن</p>
                        </div>
                    </td>
                </tr>
                <?php else: foreach ($orders as $order): ?>
                <tr>
                    <td><strong class="text-primary">#<?= $order['id'] ?></strong></td>
                    <td class="text-muted" style="font-size:.85rem; font-variant-numeric:tabular-nums;">
                        <?= date('H:i', strtotime($order['created_at'])) ?>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border"><?= $order['item_count'] ?> صنف</span>
                    </td>
                    <td class="fw-semibold"><?= number_format($order['total']) ?> <span class="text-muted fw-normal" style="font-size:.8rem;"><?= htmlspecialchars($currency) ?></span></td>
                    <td>
                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25" style="font-size:.78rem;">
                            <?= htmlspecialchars($order['payment_method'] ?? 'كاش') ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary" onclick="printReceipt(<?= $order['id'] ?>)" title="طباعة">
                            <i class="bi bi-printer"></i>
                        </button>
                        <?php if ($canCancel): ?>
                        <form method="post" style="display:inline;"
                              data-confirm="إلغاء الطلب #<?= $order['id'] ?>؟ سيتم خصم قيمته من الوردية.">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <button type="submit" name="cancel_order" class="btn btn-sm btn-outline-danger" title="إلغاء">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__.'/../views/partials/footer.php'; ?>

<script>
function printReceipt(orderId) {
    const win = window.open('print.php?id=' + orderId, '_blank', 'width=400,height=600');
    if (win) win.focus();
}
</script>
