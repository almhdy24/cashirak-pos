<?php
require_once __DIR__ . '/../../bootstrap.php';
use Middleware\AuthMiddleware;
use Core\Auth;
use Core\Security;
use Core\DB;

AuthMiddleware::handle('manage_items');

$user     = Auth::user();
$db       = DB::conn();
$currency = getSetting('currency', 'SDG');

$startDate = trim($_GET['start_date'] ?? '');
$endDate   = trim($_GET['end_date']   ?? '');
$hasFilter = ($startDate !== '' && $endDate !== '');

if ($hasFilter) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))   $endDate   = '';
    $hasFilter = ($startDate !== '' && $endDate !== '');
}

$todayStmt = $db->prepare("
    SELECT COUNT(*) as transactions,
           COALESCE(SUM(total), 0) as total_sales,
           COALESCE(AVG(total), 0) as avg_sale
    FROM orders
    WHERE status = 'active' AND DATE(created_at) = DATE('now', 'localtime')
");
$todayStmt->execute();
$todaySummary = $todayStmt->fetch(\PDO::FETCH_ASSOC);

$todayPayStmt = $db->prepare("
    SELECT payment_method, COUNT(*) as cnt, COALESCE(SUM(total), 0) as total
    FROM orders
    WHERE status = 'active' AND DATE(created_at) = DATE('now', 'localtime')
    GROUP BY payment_method ORDER BY total DESC
");
$todayPayStmt->execute();
$todayPayBreakdown = $todayPayStmt->fetchAll(\PDO::FETCH_ASSOC);

$last7Stmt = $db->prepare("
    SELECT DATE(o.created_at) as day,
           COUNT(o.id) as transactions,
           COALESCE(SUM(o.total),0) as sales,
           (SELECT COUNT(*) FROM returns r WHERE DATE(r.created_at) = DATE(o.created_at)) as returns
    FROM orders o
    WHERE o.status = 'active'
      AND DATE(o.created_at) >= DATE('now', 'localtime', '-6 days')
    GROUP BY day ORDER BY day DESC
");
$last7Stmt->execute();
$last7Days = $last7Stmt->fetchAll(\PDO::FETCH_ASSOC);

$stockAlerts = $db->query("
    SELECT id, name, stock, min_stock FROM items
    WHERE stock IS NOT NULL
      AND ((min_stock IS NOT NULL AND stock <= min_stock) OR (min_stock IS NULL AND stock <= 5))
    ORDER BY stock ASC
")->fetchAll(\PDO::FETCH_ASSOC);

$outOfStock = $db->query("
    SELECT id, name, stock FROM items WHERE stock IS NOT NULL AND stock <= 0 ORDER BY name
")->fetchAll(\PDO::FETCH_ASSOC);

$filteredData = $filteredPayment = $filteredDaily = null;
if ($hasFilter) {
    $fStmt = $db->prepare("
        SELECT COUNT(*) as transactions,
               COALESCE(SUM(total), 0) as total_sales,
               COALESCE(AVG(total), 0) as avg_sale
        FROM orders WHERE status = 'active' AND DATE(created_at) BETWEEN ? AND ?
    ");
    $fStmt->execute([$startDate, $endDate]);
    $filteredData = $fStmt->fetch(\PDO::FETCH_ASSOC);

    $fpStmt = $db->prepare("
        SELECT payment_method, COUNT(*) as cnt, COALESCE(SUM(total), 0) as total
        FROM orders WHERE status = 'active' AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY payment_method ORDER BY total DESC
    ");
    $fpStmt->execute([$startDate, $endDate]);
    $filteredPayment = $fpStmt->fetchAll(\PDO::FETCH_ASSOC);

    $fdailyStmt = $db->prepare("
        SELECT DATE(created_at) as day, COUNT(*) as transactions, COALESCE(SUM(total), 0) as sales
        FROM orders WHERE status = 'active' AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY day ORDER BY day DESC
    ");
    $fdailyStmt->execute([$startDate, $endDate]);
    $filteredDaily = $fdailyStmt->fetchAll(\PDO::FETCH_ASSOC);
}

$csrf      = Security::generateCSRFToken();
$pageTitle = getSetting('cafe_title', 'كاشيراك') . ' - التقارير';
include __DIR__ . '/../../views/partials/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-graph-up"></i> تقارير المبيعات</h2>
    <span class="text-muted" style="font-size:.85rem;"><i class="bi bi-calendar-day"></i> <?= date('Y-m-d') ?></span>
</div>

<!-- Today stat cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="stat-label">مبيعات اليوم</div>
                <div class="stat-value"><?= number_format($todaySummary['total_sales'], 0) ?></div>
                <div class="stat-sub"><?= htmlspecialchars($currency) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-receipt"></i></div>
            <div>
                <div class="stat-label">طلبات اليوم</div>
                <div class="stat-value"><?= number_format($todaySummary['transactions']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-calculator"></i></div>
            <div>
                <div class="stat-label">متوسط الطلب</div>
                <div class="stat-value"><?= number_format($todaySummary['avg_sale'], 0) ?></div>
                <div class="stat-sub"><?= htmlspecialchars($currency) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <!-- Last 7 days -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-calendar-week"></i> آخر 7 أيام</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($last7Days)): ?>
                <div class="empty-state"><i class="bi bi-calendar-x"></i><p>لا توجد مبيعات في آخر 7 أيام</p></div>
                <?php else: ?>
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>التاريخ</th><th>المبيعات</th><th class="text-center">الطلبات</th><th class="text-center">المرتجعات</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($last7Days as $day): ?>
                        <tr>
                            <td style="font-size:.85rem;"><?= htmlspecialchars($day['day']) ?></td>
                            <td class="fw-semibold"><?= number_format($day['sales'], 0) ?> <span class="text-muted fw-normal" style="font-size:.78rem;"><?= htmlspecialchars($currency) ?></span></td>
                            <td class="text-center"><?= $day['transactions'] ?></td>
                            <td class="text-center"><?= $day['returns'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Date range filter -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> تصفية بنطاق تاريخ</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-sm-4">
                        <label class="form-label">من تاريخ</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">إلى تاريخ</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                    <div class="col-sm-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> عرض
                        </button>
                    </div>
                </form>

                <?php if ($hasFilter && $filteredData): ?>
                <hr class="my-3">
                <p class="text-muted small mb-3">
                    <i class="bi bi-calendar-range"></i>
                    النتائج من <strong><?= htmlspecialchars($startDate) ?></strong> إلى <strong><?= htmlspecialchars($endDate) ?></strong>
                </p>
                <div class="row g-3 mb-3">
                    <div class="col-sm-4">
                        <div class="stat-card" style="padding:.75rem 1rem;">
                            <div class="stat-icon amber" style="width:36px;height:36px;border-radius:8px;font-size:1rem;"><i class="bi bi-cash-stack"></i></div>
                            <div>
                                <div class="stat-label">المبيعات</div>
                                <div class="stat-value" style="font-size:1.1rem;"><?= number_format($filteredData['total_sales'], 0) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="stat-card" style="padding:.75rem 1rem;">
                            <div class="stat-icon blue" style="width:36px;height:36px;border-radius:8px;font-size:1rem;"><i class="bi bi-receipt"></i></div>
                            <div>
                                <div class="stat-label">الطلبات</div>
                                <div class="stat-value" style="font-size:1.1rem;"><?= $filteredData['transactions'] ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="stat-card" style="padding:.75rem 1rem;">
                            <div class="stat-icon green" style="width:36px;height:36px;border-radius:8px;font-size:1rem;"><i class="bi bi-graph-up"></i></div>
                            <div>
                                <div class="stat-label">متوسط</div>
                                <div class="stat-value" style="font-size:1.1rem;"><?= number_format($filteredData['avg_sale'], 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($filteredPayment)): ?>
                <h6 class="mb-2">توزيع طرق الدفع</h6>
                <table class="table table-sm table-hover mb-3">
                    <thead class="table-light"><tr><th>طريقة الدفع</th><th>الطلبات</th><th>الإجمالي</th></tr></thead>
                    <tbody>
                        <?php foreach ($filteredPayment as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['payment_method']) ?></td>
                            <td><?= $row['cnt'] ?></td>
                            <td class="fw-semibold"><?= number_format($row['total'], 0) ?> <?= htmlspecialchars($currency) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if (!empty($filteredDaily)): ?>
                <h6 class="mb-2">تفاصيل يومية</h6>
                <table class="table table-sm table-hover">
                    <thead class="table-light"><tr><th>التاريخ</th><th>المبيعات</th><th>الطلبات</th></tr></thead>
                    <tbody>
                        <?php foreach ($filteredDaily as $day): ?>
                        <tr>
                            <td style="font-size:.85rem;"><?= htmlspecialchars($day['day']) ?></td>
                            <td><?= number_format($day['sales'], 0) ?> <?= htmlspecialchars($currency) ?></td>
                            <td><?= $day['transactions'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php elseif ($hasFilter): ?>
                <div class="alert alert-info mt-3 mb-0 small">لا توجد بيانات لهذه الفترة.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <!-- Today payment breakdown -->
        <?php if (!empty($todayPayBreakdown)): ?>
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-credit-card"></i> طرق الدفع — اليوم</h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($todayPayBreakdown as $row): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= htmlspecialchars($row['payment_method']) ?></span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary rounded-pill"><?= $row['cnt'] ?></span>
                            <strong><?= number_format($row['total'], 0) ?> <?= htmlspecialchars($currency) ?></strong>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stock alerts -->
        <?php if (!empty($outOfStock) || !empty($stockAlerts)): ?>
        <div class="card">
            <div class="card-header bg-white d-flex align-items-center gap-2">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle text-warning"></i> تنبيهات المخزون</h5>
                <?php if (!empty($outOfStock)): ?>
                <span class="badge bg-danger ms-auto"><?= count($outOfStock) ?> نفد</span>
                <?php endif; ?>
                <?php if (!empty($stockAlerts)): ?>
                <span class="badge bg-warning text-dark"><?= count($stockAlerts) ?> منخفض</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($outOfStock as $item): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= htmlspecialchars($item['name']) ?></span>
                        <span class="badge bg-danger">نفد</span>
                    </li>
                    <?php endforeach; ?>
                    <?php
                        $outIds = array_column($outOfStock, 'id');
                        foreach ($stockAlerts as $item):
                            if (in_array($item['id'], $outIds)) continue;
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= htmlspecialchars($item['name']) ?></span>
                        <span class="badge bg-warning text-dark"><?= $item['stock'] ?> متبقٍ</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i> لا توجد تنبيهات مخزون حالياً.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
