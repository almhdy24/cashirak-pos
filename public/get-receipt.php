<?php
require_once __DIR__.'/../app/helpers.php';
use Middleware\AuthMiddleware;
use Core\DB;

AuthMiddleware::handle('process_order');

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) die('رقم طلب غير صحيح');

$db = DB::conn();
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) die('الطلب غير موجود');

// جلب الأصناف
$stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// توليد HTML الفاتورة (بنفس شكل الطباعة السابق)
?>
<!DOCTYPE html>
<html dir="rtl">
<head><title>فاتورة #<?= $order_id ?></title></head>
<body style="font-family:monospace; text-align:center;">
    <h2>Cashirak POS</h2>
    <p><?= $order['created_at'] ?></p>
    <p>رقم الفاتورة: #<?= $order_id ?></p>
    <hr>
    <?php foreach($items as $item): ?>
        <div><?= $item['name'] ?> x <?= $item['qty'] ?> = <?= $item['subtotal'] ?> SDG</div>
    <?php endforeach; ?>
    <hr>
    <h3>الإجمالي: <?= $order['total'] ?> SDG</h3>
    <p>شكراً لزيارتكم</p>
    <?php if($order['status'] === 'cancelled'): ?>
        <p style="color:red; font-weight:bold;">--- ملغى ---</p>
    <?php endif; ?>
</body>
</html>
