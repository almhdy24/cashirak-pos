<?php
require_once __DIR__ . '/../bootstrap.php';
use Middleware\AuthMiddleware;
use Models\Shift;
use Models\Order;
use Models\OrderItem;
use Core\Auth;
use Core\Security;
use Core\DB;

AuthMiddleware::handle('manage_items');

$user     = Auth::user();
$currency = getSetting('currency', 'SDG');
$db       = DB::conn();

// Get active shift — do NOT auto-create one
$shift = Shift::getActive();
if (!$shift) {
    header('Location: admin.php?err=no_shift');
    exit;
}
$shift_id = (int)$shift['id'];

$closed     = false;
$closeData  = [];
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('CSRF غير صحيح');
    }

    $actual_cash = (float)($_POST['actual_cash'] ?? 0);
    $close_note  = trim($_POST['close_note'] ?? '');

    // Compute totals for closing
    $statsRow = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as total FROM orders WHERE shift_id = ? AND status='active'");
    $statsRow->execute([$shift_id]);
    $row          = $statsRow->fetch(\PDO::FETCH_ASSOC);
    $total_orders = (int)$row['cnt'];
    $total_sales  = (float)$row['total'];

    // Cash sales specifically
    $cashStmt = $db->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE shift_id = ? AND status='active' AND payment_method='كاش'");
    $cashStmt->execute([$shift_id]);
    $cash_sales = (float)$cashStmt->fetchColumn();

    // Expenses
    $expStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE shift_id = ?");
    $expStmt->execute([$shift_id]);
    $total_expenses = (float)$expStmt->fetchColumn();

    // Cash refunds (returns paid back in cash) leave the drawer too
    $refStmt = $db->prepare("SELECT COALESCE(SUM(total_refund),0) FROM returns WHERE shift_id = ? AND payment_method = 'كاش'");
    $refStmt->execute([$shift_id]);
    $cash_refunds = (float)$refStmt->fetchColumn();

    $opening_cash  = (float)($shift['opening_cash'] ?? 0);
    $expected_cash = $opening_cash + $cash_sales - $total_expenses - $cash_refunds;
    $difference    = $actual_cash - $expected_cash;

    Shift::closeWithReconciliation($shift_id, $actual_cash, $close_note, $total_sales, $total_orders);

    $closed = true;
    $closeData = compact('opening_cash', 'cash_sales', 'total_expenses', 'cash_refunds', 'expected_cash', 'actual_cash', 'difference', 'total_sales', 'total_orders');
}

// ── Compute preview data (for GET or on POST success display) ────────────────
if (!$closed) {
    // Payment method breakdown
    $payStmt = $db->prepare("
        SELECT payment_method, SUM(total) as total, COUNT(*) as cnt
        FROM orders WHERE shift_id = ? AND status = 'active'
        GROUP BY payment_method ORDER BY total DESC
    ");
    $payStmt->execute([$shift_id]);
    $payBreakdown = $payStmt->fetchAll(\PDO::FETCH_ASSOC);

    // Cash sales
    $cashStmt = $db->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE shift_id = ? AND status='active' AND payment_method='كاش'");
    $cashStmt->execute([$shift_id]);
    $cash_sales = (float)$cashStmt->fetchColumn();

    // Expenses
    $expStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE shift_id = ?");
    $expStmt->execute([$shift_id]);
    $total_expenses = (float)$expStmt->fetchColumn();

    // Cash refunds (returns paid back in cash) leave the drawer too
    $refStmt = $db->prepare("SELECT COALESCE(SUM(total_refund),0) FROM returns WHERE shift_id = ? AND payment_method = 'كاش'");
    $refStmt->execute([$shift_id]);
    $cash_refunds = (float)$refStmt->fetchColumn();

    // Best sellers
    $best = OrderItem::bestSellers($shift_id);

    // Stats
    $statsRow = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as total FROM orders WHERE shift_id = ? AND status='active'");
    $statsRow->execute([$shift_id]);
    $statsRow = $statsRow->fetch(\PDO::FETCH_ASSOC);

    $opening_cash  = (float)($shift['opening_cash'] ?? 0);
    $expected_cash = $opening_cash + $cash_sales - $total_expenses - $cash_refunds;
}

$csrf      = Security::generateCSRFToken();
$pageTitle = getSetting('cafe_title', 'كاشيراك') . ' - إغلاق الوردية';
include __DIR__ . '/../views/partials/header.php';
?>

<div class="container mt-4" style="max-width:700px;">
    <div class="page-header">
        <h2>
            <i class="bi bi-door-closed"></i>
            <?= $closed ? 'تقرير إغلاق الوردية' : 'إغلاق الوردية' ?> #<?= $shift_id ?>
        </h2>
    </div>
    <div class="card mx-auto">
        <div class="card-body">

        <?php if ($closed): ?>
            <!-- ── Closed: reconciliation summary ─────────────────────────── -->
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i> تم إغلاق الوردية بنجاح.
            </div>

            <ul class="list-group mb-3">
                <li class="list-group-item d-flex justify-content-between">
                    <span>عدد الطلبات</span>
                    <strong><?= number_format($closeData['total_orders']) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>إجمالي المبيعات</span>
                    <strong><?= number_format($closeData['total_sales'], 2) ?> <?= htmlspecialchars($currency) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>رصيد الافتتاح</span>
                    <strong><?= number_format($closeData['opening_cash'], 2) ?> <?= htmlspecialchars($currency) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>مبيعات كاش</span>
                    <strong><?= number_format($closeData['cash_sales'], 2) ?> <?= htmlspecialchars($currency) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>إجمالي المصروفات</span>
                    <strong class="text-danger"><?= number_format($closeData['total_expenses'], 2) ?> <?= htmlspecialchars($currency) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>مرتجعات كاش</span>
                    <strong class="text-danger"><?= number_format($closeData['cash_refunds'], 2) ?> <?= htmlspecialchars($currency) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between bg-light">
                    <span><strong>الكاش المتوقع</strong></span>
                    <strong><?= number_format($closeData['expected_cash'], 2) ?> <?= htmlspecialchars($currency) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>الكاش الفعلي المحسوب</span>
                    <strong><?= number_format($closeData['actual_cash'], 2) ?> <?= htmlspecialchars($currency) ?></strong>
                </li>
                <?php
                    $diff = $closeData['difference'];
                    $diffClass = ($diff == 0) ? 'text-success' : (abs($diff) <= 50 ? 'text-warning' : 'text-danger');
                    $diffIcon  = ($diff == 0) ? 'bi-check-circle' : (abs($diff) <= 50 ? 'bi-exclamation-triangle' : 'bi-x-circle');
                ?>
                <li class="list-group-item d-flex justify-content-between">
                    <span>الفرق</span>
                    <strong class="<?= $diffClass ?>">
                        <i class="bi <?= $diffIcon ?>"></i>
                        <?= ($diff >= 0 ? '+' : '') . number_format($diff, 2) ?> <?= htmlspecialchars($currency) ?>
                    </strong>
                </li>
            </ul>

            <div class="d-grid gap-2">
                <a href="index.php"         class="btn btn-success"><i class="bi bi-plus-circle"></i> بدء وردية جديدة</a>
                <a href="shift-history.php" class="btn btn-info"><i class="bi bi-calendar-check"></i> عرض تاريخ الورديات</a>
                <a href="admin.php"         class="btn btn-primary"><i class="bi bi-speedometer2"></i> لوحة التحكم</a>
            </div>

        <?php else: ?>
            <!-- ── GET: preview form ─────────────────────────────────────── -->
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                راجع الملخص أدناه ثم أدخل الكاش الفعلي لإغلاق الوردية. لا يمكن التراجع.
            </div>

            <!-- Financial summary -->
            <h6 class="mb-2"><i class="bi bi-cash-stack"></i> ملخص مالي</h6>
            <ul class="list-group mb-3">
                <li class="list-group-item d-flex justify-content-between">
                    <span>رصيد الافتتاح</span>
                    <strong><?= number_format($opening_cash, 2) ?> <?= htmlspecialchars($currency) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>مبيعات كاش</span>
                    <strong><?= number_format($cash_sales, 2) ?> <?= htmlspecialchars($currency) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>إجمالي المصروفات</span>
                    <strong class="text-danger"><?= number_format($total_expenses, 2) ?> <?= htmlspecialchars($currency) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>مرتجعات كاش</span>
                    <strong class="text-danger"><?= number_format($cash_refunds, 2) ?> <?= htmlspecialchars($currency) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between bg-light">
                    <span><strong>الكاش المتوقع</strong></span>
                    <strong><?= number_format($expected_cash, 2) ?> <?= htmlspecialchars($currency) ?></strong>
                </li>
            </ul>

            <!-- Payment breakdown -->
            <?php if (!empty($payBreakdown)): ?>
            <h6 class="mb-2"><i class="bi bi-credit-card"></i> توزيع طرق الدفع</h6>
            <ul class="list-group mb-3">
                <?php foreach ($payBreakdown as $pb): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><?= htmlspecialchars($pb['payment_method']) ?></span>
                    <div>
                        <span class="badge bg-secondary rounded-pill me-1"><?= $pb['cnt'] ?> طلب</span>
                        <strong><?= number_format($pb['total'], 2) ?> <?= htmlspecialchars($currency) ?></strong>
                    </div>
                </li>
                <?php endforeach; ?>
                <li class="list-group-item d-flex justify-content-between bg-light">
                    <span><strong>الإجمالي</strong></span>
                    <strong><?= number_format($statsRow['total'], 2) ?> <?= htmlspecialchars($currency) ?></strong>
                </li>
            </ul>
            <?php endif; ?>

            <!-- Best sellers -->
            <?php if (!empty($best)): ?>
            <h6 class="mb-2"><i class="bi bi-bar-chart-steps"></i> الأكثر مبيعاً</h6>
            <ul class="list-group mb-3">
                <?php foreach ($best as $b): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <?= htmlspecialchars($b['name']) ?>
                    <span class="badge bg-primary rounded-pill"><?= $b['sold'] ?> قطعة</span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <hr>

            <!-- Reconciliation form -->
            <form method="POST" class="mb-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-cash-coin"></i> الكاش الفعلي المحسوب
                        <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input type="number" name="actual_cash" class="form-control form-control-lg"
                               step="0.01" min="0" required
                               placeholder="0.00">
                        <span class="input-group-text"><?= htmlspecialchars($currency) ?></span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">ملاحظات الإغلاق (اختياري)</label>
                    <textarea name="close_note" class="form-control" rows="2"
                              placeholder="أي ملاحظات عند إغلاق الوردية..."></textarea>
                </div>
                <button type="submit" class="btn btn-danger w-100 btn-lg">
                    <i class="bi bi-door-closed-fill"></i> تأكيد إغلاق الوردية
                </button>
            </form>
            <a href="admin.php" class="btn btn-outline-secondary w-100">
                <i class="bi bi-arrow-right"></i> إلغاء والعودة
            </a>

        <?php endif; ?>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
