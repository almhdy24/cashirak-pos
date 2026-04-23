<?php
require_once __DIR__.'/../app/helpers.php';
use Middleware\AuthMiddleware;
use Services\ShiftService;
use Models\Shift;
use Models\OrderItem;
use Core\Auth;

AuthMiddleware::handle('manage_items');

$user = Auth::user();
$shift_id = Shift::getActiveOrOpen($user['id']);
$stats = ShiftService::closeShift($shift_id);
$best = OrderItem::bestSellers($shift_id);
$pageTitle = 'تقرير الوردية - Cashirak V2';
include __DIR__.'/../views/partials/header.php';
?>

<div class="container mt-5"><div class="card mx-auto" style="max-width:600px;">
    <div class="card-header bg-dark text-white"><h4>تقرير الوردية #<?= $shift_id ?></h4></div>
    <div class="card-body">
        <ul class="list-group mb-3"><li class="list-group-item d-flex justify-content-between">عدد الطلبات <strong><?= $stats['orders'] ?></strong></li>
        <li class="list-group-item d-flex justify-content-between">إجمالي المبيعات <strong><?= number_format($stats['sales']) ?> SDG</strong></li></ul>
        <h5>الأكثر مبيعاً</h5><ul class="list-group"><?php foreach($best as $b): ?><li class="list-group-item d-flex justify-content-between"><?= $b['name'] ?> <span><?= $b['sold'] ?> قطعة</span></li><?php endforeach; ?></ul>
        <hr><div class="d-grid gap-2"><a href="admin.php" class="btn btn-primary">العودة للإدارة</a><a href="index.php" class="btn btn-success">بدء وردية جديدة</a></div>
    </div>
</div></div>

<?php include __DIR__.'/../views/partials/footer.php'; ?>
