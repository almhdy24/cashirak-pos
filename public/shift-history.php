<?php
require_once __DIR__ . '/../bootstrap.php';
use Middleware\AuthMiddleware;
use Models\Shift;
use Core\Auth;
use Core\Security;

AuthMiddleware::handle('manage_items');

$shifts   = Shift::getAllClosed(100);
$currency = getSetting('currency', 'SDG');
$pageTitle = getSetting('cafe_title', 'كاشيراك') . ' - تاريخ الورديات';
include __DIR__.'/../views/partials/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-calendar-check"></i> تاريخ الورديات</h2>
    <span class="badge bg-secondary rounded-pill px-3 py-2" style="font-size:.82rem;"><?= count($shifts) ?> وردية</span>
</div>

<?php if (empty($shifts)): ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <p>لا توجد ورديات مغلقة حتى الآن</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>البداية</th>
                    <th>النهاية</th>
                    <th>فتحها</th>
                    <th>الطلبات</th>
                    <th>إجمالي المبيعات</th>
                    <th width="80"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shifts as $shift): ?>
                <tr>
                    <td><strong class="text-primary">#<?= $shift['id'] ?></strong></td>
                    <td class="text-muted" style="font-size:.85rem;">
                        <?= date('Y/m/d H:i', strtotime($shift['start_time'])) ?>
                    </td>
                    <td class="text-muted" style="font-size:.85rem;">
                        <?= $shift['end_time']
                            ? date('Y/m/d H:i', strtotime($shift['end_time']))
                            : '<span class="badge bg-success">مفتوحة</span>' ?>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border" style="font-size:.78rem;">
                            <?= htmlspecialchars($shift['opened_by_name'] ?? 'غير معروف') ?>
                        </span>
                    </td>
                    <td><?= number_format($shift['total_orders'] ?? 0) ?></td>
                    <td class="fw-semibold">
                        <?= number_format($shift['total_sales'] ?? 0) ?>
                        <span class="text-muted fw-normal" style="font-size:.8rem;"><?= htmlspecialchars($currency) ?></span>
                    </td>
                    <td>
                        <a href="shift-details.php?id=<?= $shift['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__.'/../views/partials/footer.php'; ?>
