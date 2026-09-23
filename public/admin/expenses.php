<?php
require_once __DIR__ . '/../../bootstrap.php';
use Middleware\AuthMiddleware;
use Models\Shift;
use Core\Auth;
use Core\Security;
use Core\DB;

AuthMiddleware::handle('manage_items');

$user     = Auth::user();
$db       = DB::conn();
$csrf     = Security::generateCSRFToken();
$currency = getSetting('currency', 'SDG');
$error    = '';
$success  = '';

$shift = Shift::getActive();
if (!$shift) {
    $shift = $db->query("SELECT * FROM shifts ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
}
$shift_id = $shift ? (int)$shift['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) die('CSRF غير صحيح');

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $amount      = (float)($_POST['amount'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if ($amount <= 0) {
            $error = 'المبلغ يجب أن يكون أكبر من صفر';
        } elseif ($description === '') {
            $error = 'يرجى إدخال وصف المصروف';
        } elseif (!$shift || $shift['status'] !== 'open') {
            $error = 'لا توجد وردية مفتوحة حالياً';
        } else {
            $db->prepare("INSERT INTO expenses (shift_id, user_id, amount, description) VALUES (?, ?, ?, ?)")
                ->execute([$shift_id, $user['id'], $amount, $description]);
            $success = 'تم إضافة المصروف بنجاح';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['expense_id'] ?? 0);
        $db->prepare("DELETE FROM expenses WHERE id = ? AND shift_id = ?")
            ->execute([$id, $shift_id]);
        $success = 'تم حذف المصروف';
    }

    if (!$error) {
        header('Location: expenses.php?msg=' . urlencode($success ?: 'تمت العملية'));
        exit;
    }
}

if (isset($_GET['msg'])) $success = htmlspecialchars($_GET['msg']);

$expStmt = $db->prepare("
    SELECT e.*, u.username
    FROM expenses e
    LEFT JOIN users u ON e.user_id = u.id
    WHERE e.shift_id = ?
    ORDER BY e.created_at DESC
");
$expStmt->execute([$shift_id]);
$expenses      = $expStmt->fetchAll(\PDO::FETCH_ASSOC);
$totalExpenses = array_sum(array_column($expenses, 'amount'));

$pageTitle = getSetting('cafe_title', 'كاشيراك') . ' - المصروفات';
include __DIR__ . '/../../views/partials/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-cash-coin"></i> مصروفات الوردية</h2>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (!$shift): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> لا توجد وردية نشطة أو سابقة.</div>
<?php else: ?>

<!-- Stat row -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon <?= $shift['status'] === 'open' ? 'green' : 'slate' ?>">
                <i class="bi bi-clock-history"></i>
            </div>
            <div>
                <div class="stat-label">الوردية</div>
                <div class="stat-value">#<?= $shift_id ?></div>
                <div class="stat-sub"><?= $shift['status'] === 'open' ? 'نشطة' : 'مغلقة' ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="stat-label">إجمالي المصروفات</div>
                <div class="stat-value"><?= number_format($totalExpenses, 0) ?></div>
                <div class="stat-sub"><?= htmlspecialchars($currency) ?></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon slate"><i class="bi bi-list-ul"></i></div>
            <div>
                <div class="stat-label">عدد السجلات</div>
                <div class="stat-value"><?= count($expenses) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <?php if ($shift['status'] === 'open'): ?>
    <!-- Add expense form -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-plus-circle"></i> إضافة مصروف</h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">المبلغ <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="amount" class="form-control"
                                   step="0.01" min="0.01" required placeholder="0.00">
                            <span class="input-group-text"><?= htmlspecialchars($currency) ?></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الوصف <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" required
                               maxlength="200" placeholder="وصف المصروف...">
                    </div>
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="bi bi-plus-circle"></i> إضافة
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Expenses list -->
    <div class="<?= $shift['status'] === 'open' ? 'col-md-8' : 'col-12' ?>">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> سجل المصروفات</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($expenses)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>لا توجد مصروفات لهذه الوردية</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>الوصف</th>
                                <th>المبلغ</th>
                                <th>أضافه</th>
                                <th>الوقت</th>
                                <?php if ($shift['status'] === 'open'): ?><th width="60"></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $exp): ?>
                            <tr>
                                <td><?= htmlspecialchars($exp['description']) ?></td>
                                <td class="text-danger fw-semibold">
                                    <?= number_format($exp['amount'], 0) ?> <span class="text-muted fw-normal" style="font-size:.78rem;"><?= htmlspecialchars($currency) ?></span>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($exp['username'] ?? '—') ?></td>
                                <td class="text-muted small" style="font-variant-numeric:tabular-nums;">
                                    <?= date('H:i', strtotime($exp['created_at'])) ?>
                                </td>
                                <?php if ($shift['status'] === 'open'): ?>
                                <td>
                                    <form method="post"
                                          data-confirm="حذف مصروف «<?= htmlspecialchars($exp['description']) ?>»؟">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td><strong>الإجمالي</strong></td>
                                <td colspan="<?= $shift['status'] === 'open' ? 4 : 3 ?>">
                                    <strong class="text-danger">
                                        <?= number_format($totalExpenses, 0) ?> <?= htmlspecialchars($currency) ?>
                                    </strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
