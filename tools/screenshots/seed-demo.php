<?php
/**
 * Cashirak POS — demo data seeder (screenshots only)
 *
 * Installs a FRESH copy through Core\Installer, then fills it with a
 * realistic Sudanese café: menu with barcodes/stock, a week of closed and
 * reconciled shifts, today's open shift with orders across all payment
 * methods, expenses, a return and a cancelled order.
 *
 * Never run it on a real shop — it refuses if the app is already installed.
 * Normally you don't call it directly: tools/screenshots/build.sh does.
 *
 *   php tools/screenshots/seed-demo.php
 */

require_once __DIR__ . '/../../app/Core/paths.php';
require_once CORE_PATH . '/DB.php';
require_once CORE_PATH . '/Security.php';
require_once CORE_PATH . '/Installer.php';

use Core\DB;
use Core\Installer;
use Core\Security;

if (file_exists(STORAGE_PATH . '/installed.lock')) {
    fwrite(STDERR, "Already installed — refusing to seed demo data into a real database.\n");
    exit(1);
}
if (!is_dir(STORAGE_PATH)) mkdir(STORAGE_PATH, 0777, true);

const ADMIN_USER   = 'admin';
const ADMIN_PASS   = 'admin1234';
const CASHIER_PASS = 'cashier123';

$result = (new Installer())->install([
    'cafe_title'             => 'كافتيريا النيلين',
    'currency'               => 'SDG',
    'invoice_footer'         => 'شكراً لزيارتكم — نتشرف بخدمتكم دائماً',
    'date_format'            => 'Y-m-d H:i',
    'admin_username'         => ADMIN_USER,
    'admin_password'         => ADMIN_PASS,
    'admin_password_confirm' => ADMIN_PASS,
]);
if (!$result['success']) {
    fwrite(STDERR, "Install failed: {$result['message']}\n");
    exit(1);
}

require_once APP_PATH . '/helpers.php'; // runs schema migrations (expenses, returns, …)

$db = DB::conn();
mt_srand(2026); // deterministic data => identical screenshots on every run

// Clock taken from SQLite so it matches the app's datetime('now','localtime')
$midnight = (int)$db->query("SELECT strftime('%s', date('now','localtime'))")->fetchColumn();
$at = fn(int $ts) => gmdate('Y-m-d H:i:s', $ts);

$db->beginTransaction();

// ---------- Users ----------
$db->prepare("UPDATE users SET password = ? WHERE username = 'cashier'")
   ->execute([Security::hashPassword(CASHIER_PASS)]);
$db->prepare("INSERT INTO users (username, password, role, permissions) VALUES ('sara', ?, 'cashier', '[\"process_order\"]')")
   ->execute([Security::hashPassword(CASHIER_PASS)]);
$adminId   = (int)$db->query("SELECT id FROM users WHERE username = '" . ADMIN_USER . "'")->fetchColumn();
$cashierId = (int)$db->query("SELECT id FROM users WHERE username = 'cashier'")->fetchColumn();
$saraId    = (int)$db->query("SELECT id FROM users WHERE username = 'sara'")->fetchColumn();

// ---------- Menu (replaces the installer's sample menu) ----------
$db->exec("DELETE FROM items; DELETE FROM categories;");
// [name, price, cost, barcode, stock, min_stock]
$menu = [
    ['مشروبات ساخنة', 'شاي، قهوة، كركدي', [
        ['شاي سادة', 500, 150, '', null, null], ['شاي بلبن', 800, 300, '', null, null],
        ['قهوة جبنة', 1500, 500, '', null, null], ['نسكافيه', 1500, 600, '', null, null],
        ['كركدي ساخن', 700, 200, '', null, null],
    ]],
    ['مشروبات باردة', 'عصائر طبيعية ومياه', [
        ['عصير برتقال', 1500, 700, '', 0, 5], ['عصير ليمون', 1200, 400, '', null, null],
        ['عرديب', 1000, 300, '', null, null], ['قضيم', 1000, 300, '', null, null],
        ['مياه صحة 600مل', 600, 350, '6251234500012', 48, 12], ['كوكاكولا', 1000, 650, '5449000000996', 4, 10],
    ]],
    ['سندويتشات', 'فول، طعمية، شاورما', [
        ['سندويتش فول', 2000, 900, '', null, null], ['سندويتش طعمية', 1500, 600, '', null, null],
        ['سندويتش بيض', 2500, 1100, '', null, null], ['شاورما', 4000, 2200, '', 18, 5],
        ['برجر', 4500, 2600, '', 12, 5],
    ]],
    ['وجبات', 'أطباق رئيسية', [
        ['صحن فول بالجبنة', 3500, 1500, '', null, null], ['أقاشي', 5000, 3000, '', 3, 5], ['كبدة', 4500, 2700, '', 9, 4],
    ]],
    ['حلويات', 'كيك وحلويات شرقية', [
        ['قطعة كيك', 1800, 800, '', 14, 5], ['باسطة', 1500, 600, '', null, null],
        ['لقيمات', 1000, 350, '', null, null], ['بسكويت شاي', 800, 500, '6251234500210', 30, 10],
    ]],
];
$insCat  = $db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
$insItem = $db->prepare("INSERT INTO items (name, price, cost_price, barcode, stock, min_stock, category_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
$prices = [];
foreach ($menu as [$cat, $desc, $items]) {
    $insCat->execute([$cat, $desc]);
    $catId = (int)$db->lastInsertId();
    foreach ($items as [$name, $price, $cost, $barcode, $stock, $min]) {
        $insItem->execute([$name, $price, $cost, $barcode ?: null, $stock, $min, $catId]);
        if ($stock !== 0) $prices[$name] = $price; // out-of-stock items can't be sold
    }
}

// Popularity weights so best sellers look natural
$weights = ['شاي بلبن' => 14, 'شاي سادة' => 12, 'سندويتش فول' => 11, 'قهوة جبنة' => 9, 'سندويتش طعمية' => 8,
            'عصير ليمون' => 6, 'شاورما' => 6, 'مياه صحة 600مل' => 6, 'عرديب' => 5, 'لقيمات' => 5];
$pool = [];
foreach ($prices as $name => $_) $pool = array_merge($pool, array_fill(0, $weights[$name] ?? 2, $name));
$payPool = array_merge(array_fill(0, 11, 'كاش'), array_fill(0, 6, 'بنكك'), array_fill(0, 2, 'ماي كاشي'), ['تحويل بنكي']);
$pick = fn(array $a) => $a[mt_rand(0, count($a) - 1)];

$insShift = $db->prepare("INSERT INTO shifts (start_time, end_time, status, opened_by, opening_cash) VALUES (?, ?, ?, ?, ?)");
$insOrder = $db->prepare("INSERT INTO orders (total, created_at, shift_id, cashier_id, status, payment_method) VALUES (?, ?, ?, ?, 'active', ?)");
$insLine  = $db->prepare("INSERT INTO order_items (order_id, name, qty, price, subtotal) VALUES (?, ?, ?, ?, ?)");
$insAudit = $db->prepare("INSERT INTO audit_logs (action, user_id, entity_id, details, created_at) VALUES (?, ?, ?, ?, ?)");
$insExp   = $db->prepare("INSERT INTO expenses (shift_id, user_id, amount, description, created_at) VALUES (?, ?, ?, ?, ?)");

$expenseIdeas = [['ثلج', 3000], ['خبز للسندويتشات', 6000], ['غاز', 15000], ['سكر وشاي', 9000], ['مواصلات', 2500], ['أكياس وأكواب', 4000]];

/** One shift with $count orders starting at $start; closed shifts are reconciled. */
$makeShift = function (int $start, int $count, bool $open) use ($db, $insShift, $insOrder, $insLine, $insAudit, $insExp, $pool, $prices, $payPool, $pick, $at, $expenseIdeas, $adminId, $cashierId, $saraId) {
    $openingCash = $pick([10000, 15000, 20000]);
    $insShift->execute([$at($start), $open ? null : $at($start + 9 * 3600), $open ? 'open' : 'closed', $adminId, $openingCash]);
    $shiftId = (int)$db->lastInsertId();
    $cashier = $open ? $cashierId : $pick([$cashierId, $saraId]);

    $t = $start;
    for ($i = 0; $i < $count; $i++) {
        $t += mt_rand(4, 14) * 60;
        $lines = [];
        for ($j = 0, $n = mt_rand(1, 4); $j < $n; $j++) {
            $name = $pick($pool);
            $lines[$name] = ($lines[$name] ?? 0) + mt_rand(1, 2);
        }
        $total = 0;
        foreach ($lines as $name => $qty) $total += $qty * $prices[$name];
        $pay = $pick($payPool);
        $insOrder->execute([$total, $at($t), $shiftId, $cashier, $pay]);
        $orderId = (int)$db->lastInsertId();
        foreach ($lines as $name => $qty) $insLine->execute([$orderId, $name, $qty, $prices[$name], $qty * $prices[$name]]);
        $insAudit->execute(['order_created', $cashier, $orderId,
            json_encode(['total' => $total, 'payment_method' => $pay, 'items_count' => count($lines)], JSON_UNESCAPED_UNICODE), $at($t)]);
    }

    $expenses = 0;
    foreach (array_slice($expenseIdeas, mt_rand(0, 3), $open ? 3 : 2) as $k => [$desc, $amount]) {
        $insExp->execute([$shiftId, $adminId, $amount, $desc, $at($start + (40 + 70 * $k) * 60)]);
        $expenses += $amount;
    }

    if (!$open) {
        $s = $db->query("SELECT COUNT(*) c, COALESCE(SUM(total),0) s, COALESCE(SUM(CASE WHEN payment_method='كاش' THEN total END),0) cash
                         FROM orders WHERE shift_id = $shiftId AND status = 'active'")->fetch(PDO::FETCH_ASSOC);
        $expected = $openingCash + $s['cash'] - $expenses;
        $diff     = $pick([0, 0, 0, -500, 500, -1000]);
        $db->prepare("UPDATE shifts SET total_sales = ?, total_orders = ?, actual_cash = ?, close_note = ? WHERE id = ?")
           ->execute([$s['s'], $s['c'], $expected + $diff, $diff === 0 ? 'الدرج مطابق' : 'فرق بسيط في الفكة', $shiftId]);
    }
    return $shiftId;
};

for ($d = 6; $d >= 1; $d--) $makeShift($midnight - $d * 86400 + 7 * 3600, mt_rand(30, 44), false);
$openShift = $makeShift($midnight + 7 * 3600, 26, true);

// A cancelled order and a partial return in today's shift
$ids = $db->query("SELECT id FROM orders WHERE shift_id = $openShift ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$cancelId = (int)$ids[5];
$cancelAt = $at($midnight + 9 * 3600);
$db->prepare("UPDATE orders SET status = 'cancelled', cancelled_at = ? WHERE id = ?")->execute([$cancelAt, $cancelId]);
$insAudit->execute(['order_cancelled', $adminId, $cancelId, json_encode(['previous_total' => $db->query("SELECT total FROM orders WHERE id = $cancelId")->fetchColumn()]), $cancelAt]);

$retOrder = (int)$ids[9];
$line = $db->query("SELECT * FROM order_items WHERE order_id = $retOrder ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$db->prepare("INSERT INTO returns (order_id, user_id, shift_id, total_refund, reason, payment_method, created_at) VALUES (?, ?, ?, ?, ?, 'كاش', ?)")
   ->execute([$retOrder, $cashierId, $openShift, $line['price'], 'الزبون غيّر رأيه', $at($midnight + 10 * 3600)]);
$retId = (int)$db->lastInsertId();
$db->prepare("INSERT INTO return_items (return_id, name, qty, price, subtotal) VALUES (?, ?, 1, ?, ?)")
   ->execute([$retId, $line['name'], $line['price'], $line['price']]);

$db->commit();

$s = $db->query("SELECT COUNT(*) c, SUM(total) s FROM orders WHERE shift_id = $openShift AND status = 'active'")->fetch(PDO::FETCH_ASSOC);
echo "Demo data ready: " . count($menu) . " categories, shift #$openShift open with {$s['c']} orders (" . number_format($s['s']) . " SDG).\n";
echo "Login: " . ADMIN_USER . ' / ' . ADMIN_PASS . "  —  cashier / " . CASHIER_PASS . "\n";
