<?php
require_once __DIR__ . '/../bootstrap.php';
use Core\DB;
use Core\Auth;

Auth::requireLogin();

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) die('رقم الطلب غير صحيح');

$db = DB::conn();

$stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(\PDO::FETCH_ASSOC);
if (!$order) die('الطلب غير موجود');

$stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$cafe_title      = getSetting('cafe_title',     'كاشيراك');
$invoice_footer  = getSetting('invoice_footer', 'شكراً لزيارتكم');
$date_format     = getSetting('date_format',    'Y-m-d H:i:s');
$currency        = getSetting('currency',       'SDG');

// Payment method is now stored as display name directly
$payment_label = !empty($order['payment_method']) ? $order['payment_method'] : 'كاش';

try {
    $created_date = date($date_format, strtotime($order['created_at']));
} catch (\Throwable $e) {
    $created_date = $order['created_at'];
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>فاتورة #<?= $order_id ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            direction: rtl;
            text-align: center;
            margin: 0;
            padding: 10px;
            background: #fff;
            color: #111;
        }
        .receipt {
            max-width: 280px;
            margin: 0 auto;
            font-size: 13px;
            line-height: 1.6;
        }
        .shop-name { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
        .meta { font-size: 11px; color: #555; margin-bottom: 8px; }
        .divider { border-top: 1px dashed #999; margin: 8px 0; }
        .item-row { display: flex; justify-content: space-between; text-align: right; }
        .item-row .item-name { flex: 1; text-align: right; }
        .item-row .item-total { min-width: 70px; text-align: left; direction: ltr; }
        .total-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 15px; margin-top: 4px; }
        .cancelled { color: red; font-weight: bold; font-size: 14px; }
        .footer { margin-top: 10px; font-size: 11px; color: #666; }
        .no-print { margin-top: 16px; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="window.print(); setTimeout(() => window.close(), 1500);">
<div class="receipt">
    <div class="shop-name"><?= htmlspecialchars($cafe_title) ?></div>
    <div class="meta">
        <div><?= htmlspecialchars($created_date) ?></div>
        <div>فاتورة رقم: <?= $order_id ?></div>
        <div>طريقة الدفع: <?= htmlspecialchars($payment_label) ?></div>
    </div>

    <div class="divider"></div>

    <?php foreach ($items as $item): ?>
    <div class="item-row">
        <span class="item-name"><?= htmlspecialchars($item['name']) ?> × <?= $item['qty'] ?></span>
        <span class="item-total"><?= number_format($item['subtotal']) ?> <?= htmlspecialchars($currency) ?></span>
    </div>
    <?php endforeach; ?>

    <div class="divider"></div>

    <div class="total-row">
        <span>الإجمالي</span>
        <span><?= number_format($order['total']) ?> <?= htmlspecialchars($currency) ?></span>
    </div>

    <?php if ($order['status'] === 'cancelled'): ?>
    <div class="divider"></div>
    <div class="cancelled">✕ فاتورة ملغاة</div>
    <?php endif; ?>

    <div class="divider"></div>
    <div class="footer"><?= htmlspecialchars($invoice_footer) ?></div>
</div>

<div class="no-print">
    <button onclick="window.print();">طباعة</button>
    <button onclick="window.close();">إغلاق</button>
</div>
</body>
</html>
