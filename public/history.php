<?php
require_once __DIR__.'/../app/helpers.php';
use Middleware\AuthMiddleware;
use Models\Order;
use Models\Shift;
use Core\Auth;
use Core\DB;

AuthMiddleware::handle('process_order'); // أو صلاحية منفصلة لاحقاً

$user = Auth::user();
$shift_id = Shift::getActiveOrOpen($user['id']);
$db = DB::conn();

// جلب الطلبات النشطة (active) لهذه الوردية
$stmt = $db->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o 
    WHERE o.shift_id = ? AND o.status = 'active'
    ORDER BY o.created_at DESC
");
$stmt->execute([$shift_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// معالجة طلب الإلغاء (POST)
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    if (!\Core\Security::validateCSRFToken($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    $order_id = (int)$_POST['order_id'];
    // استدعاء API الإلغاء (نقوم به مباشرة هنا أو ننشئ ملف منفصل)
    require_once __DIR__.'/../app/Services/OrderService.php';
    try {
        $result = \Services\OrderService::cancelOrder($order_id, $user['id']);
        $message = '<div class="alert alert-success">تم إلغاء الطلب #' . $order_id . '</div>';
        // إعادة التوجيه لتحديث الصفحة
        header("Location: history.php?msg=cancelled");
        exit;
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">خطأ: ' . $e->getMessage() . '</div>';
    }
}

$csrf = \Core\Security::generateCSRFToken();
$pageTitle = 'سجل الفواتير - Cashirak V2';
include __DIR__.'/../views/partials/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-clock-history"></i> سجل فواتير الوردية #<?= $shift_id ?></h2>
        <div>
            <a href="index.php" class="btn btn-outline-primary"><i class="bi bi-cart"></i> العودة للكاشير</a>
            <?php if(Auth::hasPermission('manage_items')): ?>
            <a href="admin.php" class="btn btn-outline-secondary"><i class="bi bi-speedometer2"></i> الإدارة</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> خروج</a>
        </div>
    </div>

    <?= $message ?>

    <div class="card">
        <div class="card-header bg-light">
            <h5>الطلبات النشطة (<?= count($orders) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>الوقت</th>
                        <th>عدد الأصناف</th>
                        <th>الإجمالي</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">لا توجد طلبات في هذه الوردية</td></tr>
                    <?php else: ?>
                        <?php foreach($orders as $order): ?>
                        <tr>
                            <td><strong>#<?= $order['id'] ?></strong></td>
                            <td><?= date('H:i:s', strtotime($order['created_at'])) ?></td>
                            <td><?= $order['item_count'] ?></td>
                            <td><?= number_format($order['total']) ?> SDG</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="printReceipt(<?= $order['id'] ?>)">
                                    <i class="bi bi-printer"></i> طباعة
                                </button>
                                <form method="post" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من إلغاء هذا الطلب؟ سيتم خصم قيمته من الوردية.')">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <button type="submit" name="cancel_order" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-x-circle"></i> إلغاء
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function printReceipt(orderId) {
    // نفتح نافذة طباعة مخصصة أو نرسل طلب لجلب تفاصيل الفاتورة
    fetch('get-receipt.php?id=' + orderId)
        .then(r => r.text())
        .then(html => {
            const printWindow = window.open('', '_blank', 'width=300,height=400');
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            setTimeout(() => printWindow.close(), 2000);
        });
}
</script>

<?php include __DIR__.'/../views/partials/footer.php'; ?>
