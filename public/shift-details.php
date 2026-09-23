<?php
require_once __DIR__ . '/../bootstrap.php';
use Middleware\AuthMiddleware;
use Models\Shift;
use Core\DB;
use Core\Auth;

AuthMiddleware::handle('manage_items');

$shift_id = (int)($_GET['id'] ?? 0);
if (!$shift_id) {
    header('Location: shift-history.php');
    exit;
}

$shift = Shift::getById($shift_id);
if (!$shift || $shift['status'] !== 'closed') {
    die('الوردية غير موجودة أو لا تزال مفتوحة.');
}

$stats    = Shift::getDetailedStats($shift_id);
$currency = getSetting('currency', 'SDG');

// Payment breakdown for this shift
$db = DB::conn();
$payBreakdown = $db->prepare("
    SELECT payment_method, COUNT(*) as cnt, COALESCE(SUM(total),0) as total
    FROM orders WHERE shift_id = ? AND status = 'active'
    GROUP BY payment_method ORDER BY total DESC
");
$payBreakdown->execute([$shift_id]);
$payBreakdown = $payBreakdown->fetchAll(\PDO::FETCH_ASSOC);

$pageTitle = getSetting('cafe_title', 'كاشيراك') . ' - تفاصيل الوردية #' . $shift_id;
include __DIR__.'/../views/partials/header.php';

// Duration
$start = strtotime($shift['start_time']);
$end   = strtotime($shift['end_time']);
$durH  = floor(($end - $start) / 3600);
$durM  = floor((($end - $start) % 3600) / 60);
?>

<div class="page-header">
    <h2><i class="bi bi-file-text"></i> تقرير الوردية #<?= $shift_id ?></h2>
    <a href="shift-history.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-right"></i> العودة للقائمة
    </a>
</div>

<!-- Stat row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-receipt"></i></div>
            <div>
                <div class="stat-label">إجمالي الطلبات</div>
                <div class="stat-value"><?= number_format($stats['total_orders']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="stat-label">إجمالي المبيعات</div>
                <div class="stat-value"><?= number_format($stats['total_sales']) ?></div>
                <div class="stat-sub"><?= htmlspecialchars($currency) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-graph-up"></i></div>
            <div>
                <div class="stat-label">متوسط الطلب</div>
                <div class="stat-value"><?= $stats['total_orders'] ? number_format($stats['total_sales'] / $stats['total_orders'], 0) : 0 ?></div>
                <div class="stat-sub"><?= htmlspecialchars($currency) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon slate"><i class="bi bi-clock"></i></div>
            <div>
                <div class="stat-label">مدة الوردية</div>
                <div class="stat-value"><?= $durH ?>h <?= $durM ?>m</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Shift info -->
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-info-circle"></i> معلومات الوردية</h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">فتحها</span>
                        <strong><?= htmlspecialchars($shift['opened_by_name'] ?? '—') ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">وقت البداية</span>
                        <span style="font-size:.85rem;"><?= date('Y/m/d H:i', strtotime($shift['start_time'])) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span class="text-muted">وقت النهاية</span>
                        <span style="font-size:.85rem;"><?= date('Y/m/d H:i', strtotime($shift['end_time'])) ?></span>
                    </li>
                    <?php if (!empty($shift['close_note'])): ?>
                    <li class="list-group-item">
                        <div class="text-muted" style="font-size:.78rem;">ملاحظات الإغلاق</div>
                        <div style="font-size:.88rem;"><?= htmlspecialchars($shift['close_note']) ?></div>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Payment breakdown -->
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-credit-card"></i> توزيع طرق الدفع</h5>
            </div>
            <?php if (!empty($payBreakdown)): ?>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($payBreakdown as $pb): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= htmlspecialchars($pb['payment_method']) ?></span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary rounded-pill"><?= $pb['cnt'] ?> طلب</span>
                            <strong><?= number_format($pb['total']) ?> <?= htmlspecialchars($currency) ?></strong>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php else: ?>
            <div class="card-body"><p class="text-muted mb-0">لا توجد بيانات</p></div>
            <?php endif; ?>
        </div>

        <!-- Best sellers -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-star-fill text-warning"></i> الأكثر مبيعاً</h5>
            </div>
            <?php if (!empty($stats['best_sellers'])): ?>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($stats['best_sellers'] as $item): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= htmlspecialchars($item['name']) ?>
                        <span class="badge bg-primary rounded-pill"><?= $item['sold'] ?> قطعة</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php else: ?>
            <div class="card-body">
                <div class="empty-state" style="padding:1.5rem 1rem;">
                    <i class="bi bi-bar-chart" style="font-size:1.5rem;"></i>
                    <p>لا توجد مبيعات مسجلة</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__.'/../views/partials/footer.php'; ?>
