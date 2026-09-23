<?php
require_once __DIR__ . '/../bootstrap.php';
use Middleware\AuthMiddleware;
use Models\Shift;
use Core\Auth;
use Core\Security;
use Core\DB;

AuthMiddleware::handle('process_order');

$user     = Auth::user();
$db       = DB::conn();
$csrf     = Security::generateCSRFToken();
$currency = getSetting('currency', 'SDG');

$error    = '';
$success  = '';
$order    = null;
$orderItems = [];

// Active shift
$shift_id = Shift::getActiveOrOpen($user['id']);

// ── POST: process return ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) die('CSRF غير صحيح');

    $order_id      = (int)($_POST['order_id'] ?? 0);
    $reason        = trim($_POST['reason'] ?? '');
    $pay_method    = trim($_POST['payment_method'] ?? 'كاش');
    $return_qtys   = $_POST['return_qty'] ?? [];

    // Load order
    $orderStmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND status = 'active'");
    $orderStmt->execute([$order_id]);
    $order = $orderStmt->fetch(\PDO::FETCH_ASSOC);

    if (!$order) {
        $error = 'الطلب غير موجود أو تم إلغاؤه';
    } else {
        // Check for existing return
        $existingReturn = $db->prepare("SELECT COUNT(*) FROM returns WHERE order_id = ?");
        $existingReturn->execute([$order_id]);
        if ($existingReturn->fetchColumn() > 0) {
            $error = 'تم إرجاع هذا الطلب من قبل. لا يمكن إرجاعه مرة أخرى.';
        } else {
            // Load order items
            $itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $itemsStmt->execute([$order_id]);
            $origItems = $itemsStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Build return items
            $toReturn    = [];
            $totalRefund = 0.0;

            foreach ($origItems as $oi) {
                $oi_id   = (int)$oi['id'];
                $qty_ret = (int)($return_qtys[$oi_id] ?? 0);
                if ($qty_ret <= 0) continue;
                if ($qty_ret > (int)$oi['qty']) {
                    $error = 'كمية الإرجاع تتجاوز الكمية الأصلية للصنف: ' . htmlspecialchars($oi['name']);
                    break;
                }
                $subtotal     = (float)$oi['price'] * $qty_ret;
                $totalRefund += $subtotal;
                $toReturn[]   = [
                    'name'     => $oi['name'],
                    'qty'      => $qty_ret,
                    'price'    => (float)$oi['price'],
                    'subtotal' => $subtotal,
                ];
            }

            if (!$error && empty($toReturn)) {
                $error = 'يرجى اختيار صنف واحد على الأقل للإرجاع';
            }

            if (!$error) {
                try {
                    $db->beginTransaction();

                    // Insert return record
                    $db->prepare("
                        INSERT INTO returns (order_id, user_id, shift_id, total_refund, reason, payment_method)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute([$order_id, $user['id'], $shift_id, $totalRefund, $reason, $pay_method]);
                    $return_id = (int)$db->lastInsertId();

                    // Insert return items and restore stock
                    foreach ($toReturn as $ri) {
                        // Look up item_id by name
                        $itemRow = $db->prepare("SELECT id FROM items WHERE name = ? LIMIT 1");
                        $itemRow->execute([$ri['name']]);
                        $item_id = $itemRow->fetchColumn() ?: null;

                        $db->prepare("
                            INSERT INTO return_items (return_id, name, qty, price, subtotal, item_id)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ")->execute([$return_id, $ri['name'], $ri['qty'], $ri['price'], $ri['subtotal'], $item_id]);

                        // Restore stock if item exists
                        if ($item_id) {
                            $db->prepare("
                                UPDATE items SET stock = stock + ?
                                WHERE id = ? AND stock IS NOT NULL
                            ")->execute([$ri['qty'], $item_id]);
                        }
                    }

                    // Audit log
                    $db->prepare("
                        INSERT INTO audit_logs (user_id, action, details, created_at)
                        VALUES (?, 'return', ?, datetime('now','localtime'))
                    ")->execute([
                        $user['id'],
                        json_encode([
                            'return_id' => $return_id,
                            'order_id'  => $order_id,
                            'refund'    => $totalRefund,
                            'items'     => count($toReturn),
                        ], JSON_UNESCAPED_UNICODE)
                    ]);

                    $db->commit();
                    $success = sprintf('تم إرجاع الطلب #%d بنجاح — مبلغ الاسترداد: %s %s',
                        $order_id, number_format($totalRefund, 2), $currency);
                    $order      = null; // reset to show search form
                    $orderItems = [];
                } catch (\Throwable $e) {
                    $db->rollBack();
                    $error = 'خطأ في معالجة الإرجاع: ' . $e->getMessage();
                }
            }
        }
    }
}

// ── GET: search for order ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['order_id']) && $_GET['order_id'] !== '') {
    $search_id = (int)$_GET['order_id'];
    $orderStmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $orderStmt->execute([$search_id]);
    $order = $orderStmt->fetch(\PDO::FETCH_ASSOC);

    if (!$order) {
        $error = 'لم يُعثر على طلب بهذا الرقم';
    } else {
        $itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$search_id]);
        $orderItems = $itemsStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Check if already returned
        $arStmt = $db->prepare("SELECT COUNT(*) FROM returns WHERE order_id = ?");
        $arStmt->execute([$search_id]);
        $alreadyReturned = (int)$arStmt->fetchColumn();
    }
}

// ── Load shift returns list ──────────────────────────────────────────────────
$returnsStmt = $db->prepare("
    SELECT r.*, u.username,
           (SELECT COUNT(*) FROM return_items WHERE return_id = r.id) as items_count
    FROM returns r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.shift_id = ?
    ORDER BY r.created_at DESC
    LIMIT 50
");
$returnsStmt->execute([$shift_id]);
$shiftReturns = $returnsStmt->fetchAll(\PDO::FETCH_ASSOC);

// Payment methods for the refund form
try {
    $payMethods = $db->query("SELECT name FROM payment_methods WHERE is_active=1 ORDER BY sort_order, id")
        ->fetchAll(\PDO::FETCH_COLUMN);
} catch (\Throwable $e) {
    $payMethods = ['كاش', 'بنكك', 'ماي كاشي', 'تحويل بنكي'];
}
if (empty($payMethods)) $payMethods = ['كاش'];

$pageTitle = getSetting('cafe_title', 'كاشيراك') . ' - المرتجعات';
include __DIR__ . '/../views/partials/header.php';
?>

<div class="container mt-4">
    <div class="page-header">
        <h2><i class="bi bi-arrow-return-right"></i> المرتجعات</h2>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><i class="bi bi-x-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- ── Search form ───────────────────────────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-search"></i> بحث بطلب</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">رقم الطلب</label>
                    <input type="number" name="order_id" class="form-control form-control-lg"
                           min="1" required placeholder="أدخل رقم الطلب..."
                           value="<?= htmlspecialchars((string)($_GET['order_id'] ?? '')) ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100 btn-lg">
                        <i class="bi bi-search"></i> بحث
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Order details + return form ───────────────────────────────────── -->
    <?php if ($order && !empty($orderItems)): ?>
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-receipt"></i> تفاصيل الطلب #<?= $order['id'] ?>
            </h5>
            <div>
                <span class="badge bg-<?= $order['status'] === 'active' ? 'success' : 'secondary' ?>">
                    <?= $order['status'] === 'active' ? 'نشط' : htmlspecialchars($order['status']) ?>
                </span>
                <span class="badge bg-info ms-1"><?= htmlspecialchars($order['payment_method']) ?></span>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <small class="text-muted">تاريخ الطلب</small>
                    <div><?= htmlspecialchars($order['created_at'] ?? '—') ?></div>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">القيمة الإجمالية</small>
                    <div><strong><?= number_format($order['total'], 2) ?> <?= htmlspecialchars($currency) ?></strong></div>
                </div>
            </div>

            <?php if ($alreadyReturned > 0): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                تم إرجاع هذا الطلب من قبل. لا يمكن إرجاعه مرة أخرى.
            </div>
            <?php elseif ($order['status'] !== 'active'): ?>
            <div class="alert alert-secondary">
                هذا الطلب غير نشط ولا يمكن إرجاعه.
            </div>
            <?php else: ?>
            <form method="POST" data-confirm="تأكيد عملية الإرجاع؟ لا يمكن التراجع عن هذه العملية.">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">

                <h6 class="mb-2"><i class="bi bi-list-check"></i> اختر الأصناف المراد إرجاعها</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>الصنف</th>
                                <th>السعر</th>
                                <th>الكمية الأصلية</th>
                                <th>كمية الإرجاع</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $oi): ?>
                            <tr>
                                <td><?= htmlspecialchars($oi['name']) ?></td>
                                <td><?= number_format($oi['price'], 2) ?> <?= htmlspecialchars($currency) ?></td>
                                <td><?= $oi['qty'] ?></td>
                                <td>
                                    <input type="number" name="return_qty[<?= $oi['id'] ?>]"
                                           class="form-control form-control-sm"
                                           style="width:90px"
                                           min="0" max="<?= $oi['qty'] ?>" value="0">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">طريقة استرداد المبلغ</label>
                        <select name="payment_method" class="form-select">
                            <?php foreach ($payMethods as $pm): ?>
                            <option value="<?= htmlspecialchars($pm) ?>"><?= htmlspecialchars($pm) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">سبب الإرجاع (اختياري)</label>
                        <input type="text" name="reason" class="form-control"
                               placeholder="سبب الإرجاع..." maxlength="200">
                    </div>
                </div>

                <button type="submit" class="btn btn-warning btn-lg">
                    <i class="bi bi-arrow-return-right"></i> تأكيد الإرجاع
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif ($order && empty($orderItems)): ?>
    <div class="alert alert-warning">الطلب موجود ولكن لا يحتوي على أصناف.</div>
    <?php endif; ?>

    <!-- ── Current shift returns list ────────────────────────────────────── -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="bi bi-clock-history"></i>
                مرتجعات الوردية الحالية
                <span class="badge bg-secondary ms-1"><?= count($shiftReturns) ?></span>
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($shiftReturns)): ?>
            <p class="text-muted text-center p-4 mb-0"><i class="bi bi-inbox"></i> لا توجد مرتجعات في هذه الوردية</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>طلب</th>
                            <th>مبلغ الاسترداد</th>
                            <th>طريقة الاسترداد</th>
                            <th>عدد الأصناف</th>
                            <th>بواسطة</th>
                            <th>الوقت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shiftReturns as $ret): ?>
                        <tr>
                            <td class="text-muted small"><?= $ret['id'] ?></td>
                            <td><a href="?order_id=<?= $ret['order_id'] ?>">#<?= $ret['order_id'] ?></a></td>
                            <td class="text-warning fw-semibold">
                                <?= number_format($ret['total_refund'], 2) ?> <?= htmlspecialchars($currency) ?>
                            </td>
                            <td><?= htmlspecialchars($ret['payment_method']) ?></td>
                            <td><?= $ret['items_count'] ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($ret['username'] ?? '—') ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($ret['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
