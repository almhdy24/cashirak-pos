<?php
require_once __DIR__ . '/../../bootstrap.php';
use Middleware\AuthMiddleware;
use Models\User;
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

$availablePermissions = [
    'process_order' => 'استخدام الكاشير (إنشاء طلبات)',
    'manage_items'  => 'إدارة الأصناف والإدارة',
];

function validateUsername(string $u): string {
    if (strlen($u) < 3 || strlen($u) > 32) return 'اسم المستخدم يجب أن يكون بين 3 و 32 حرفاً';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $u)) return 'اسم المستخدم يحتوي على أحرف غير مسموح بها';
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) die('CSRF غير صحيح');

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $role     = $_POST['role'] ?? 'cashier';
        $perms    = $role === 'admin' ? ['process_order', 'manage_items'] : [];
        if ($role !== 'admin') {
            foreach (array_keys($availablePermissions) as $p) {
                if (!empty($_POST['perm_' . $p])) $perms[] = $p;
            }
        }
        $err = validateUsername($username);
        if ($err)                             { $error = $err; }
        elseif (strlen($password) < 8)        { $error = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'; }
        elseif ($password !== $confirm)       { $error = 'كلمتا المرور غير متطابقتين'; }
        elseif (User::findByUsername($username)) { $error = 'اسم المستخدم مستخدم بالفعل'; }
        else {
            User::create($username, Security::hashPassword($password), $role, $perms);
            $success = 'تم إضافة المستخدم بنجاح';
        }
    } elseif ($action === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $role     = $_POST['role'] ?? 'cashier';
        $perms    = $role === 'admin' ? ['process_order', 'manage_items'] : [];
        if ($role !== 'admin') {
            foreach (array_keys($availablePermissions) as $p) {
                if (!empty($_POST['perm_' . $p])) $perms[] = $p;
            }
        }
        $err = validateUsername($username);
        if ($err)                                           { $error = $err; }
        elseif ($password !== '' && strlen($password) < 8) { $error = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'; }
        elseif ($password !== '' && $password !== $confirm) { $error = 'كلمتا المرور غير متطابقتين'; }
        else {
            $dupStmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $dupStmt->execute([$username, $id]);
            if ($dupStmt->fetch()) {
                $error = 'اسم المستخدم مستخدم من قِبَل مستخدم آخر';
            } else {
                if ($password !== '') {
                    $db->prepare("UPDATE users SET username=?, password=?, role=?, permissions=? WHERE id=?")
                        ->execute([$username, Security::hashPassword($password), $role, json_encode($perms), $id]);
                } else {
                    $db->prepare("UPDATE users SET username=?, role=?, permissions=? WHERE id=?")
                        ->execute([$username, $role, json_encode($perms), $id]);
                }
                $success = 'تم تحديث المستخدم بنجاح';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$user['id']) {
            $error = 'لا يمكنك حذف حسابك الخاص';
        } else {
            $adminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
            $roleStmt = $db->prepare("SELECT role FROM users WHERE id=?");
            $roleStmt->execute([$id]);
            $targetRole = $roleStmt->fetchColumn();
            if ($targetRole === 'admin' && $adminCount <= 1) {
                $error = 'لا يمكن حذف آخر مسؤول في النظام';
            } else {
                $db->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
                $success = 'تم حذف المستخدم';
            }
        }
    }

    if (!$error) {
        header('Location: users.php?msg=' . urlencode($success ?: 'تمت العملية'));
        exit;
    }
}

if (isset($_GET['msg'])) $success = htmlspecialchars($_GET['msg']);

$users = $db->query("SELECT id, username, role, permissions FROM users ORDER BY role DESC, username")->fetchAll(\PDO::FETCH_ASSOC);

$pageTitle = getSetting('cafe_title', 'كاشيراك') . ' - إدارة المستخدمين';
include __DIR__ . '/../../views/partials/header.php';
?>

<div class="page-header">
    <h2><i class="bi bi-people"></i> إدارة المستخدمين</h2>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-person-plus"></i> إضافة مستخدم
    </button>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-people-fill"></i> المستخدمون (<?= count($users) ?>)</h5>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>اسم المستخدم</th>
                    <th>الدور</th>
                    <th>الصلاحيات</th>
                    <th width="110">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <?php
                    $perms = json_decode($u['permissions'] ?? '[]', true) ?: [];
                    $isMe  = ((int)$u['id'] === (int)$user['id']);
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($u['username']) ?></strong>
                        <?php if ($isMe): ?><span class="badge bg-info ms-1" style="font-size:.7rem;">أنت</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['role'] === 'admin'): ?>
                        <span class="badge bg-danger">مسؤول</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">كاشير</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['role'] === 'admin'): ?>
                        <span class="text-muted small">كامل الصلاحيات</span>
                        <?php elseif (empty($perms)): ?>
                        <span class="text-muted small">بدون صلاحيات</span>
                        <?php else: ?>
                        <?php foreach ($perms as $p): ?>
                        <span class="badge bg-light text-dark border me-1" style="font-size:.73rem;">
                            <?= htmlspecialchars($availablePermissions[$p] ?? $p) ?>
                        </span>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-warning edit-user-btn"
                                data-id="<?= $u['id'] ?>"
                                data-username="<?= htmlspecialchars($u['username']) ?>"
                                data-role="<?= htmlspecialchars($u['role']) ?>"
                                data-perms='<?= htmlspecialchars(json_encode($perms)) ?>'
                                title="تعديل">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if (!$isMe): ?>
                        <form method="post" style="display:inline;"
                              data-confirm="حذف المستخدم «<?= htmlspecialchars($u['username']) ?>» نهائياً؟">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="حذف">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> إضافة مستخدم جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">اسم المستخدم <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" required
                           pattern="[a-zA-Z0-9_]{3,32}">
                    <div class="form-text">3-32 حرف: أحرف إنجليزية، أرقام، _</div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col">
                        <label class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>
                    <div class="col">
                        <label class="form-label">التأكيد <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="8">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">الدور <span class="text-danger">*</span></label>
                    <select name="role" id="add-role" class="form-select" onchange="toggleAddPerms(this.value)">
                        <option value="cashier">كاشير</option>
                        <option value="admin">مسؤول (Admin)</option>
                    </select>
                </div>
                <div id="add-perms-div" class="mb-1">
                    <label class="form-label">الصلاحيات</label>
                    <?php foreach ($availablePermissions as $pKey => $pLabel): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               name="perm_<?= $pKey ?>" id="add-perm-<?= $pKey ?>" value="1">
                        <label class="form-check-label" for="add-perm-<?= $pKey ?>">
                            <?= htmlspecialchars($pLabel) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-person-check"></i> إضافة</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-user-id">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> تعديل المستخدم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">اسم المستخدم <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="edit-user-username" class="form-control" required
                           pattern="[a-zA-Z0-9_]{3,32}">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col">
                        <label class="form-label">كلمة المرور الجديدة <small class="text-muted">(فارغة = لا تغيير)</small></label>
                        <input type="password" name="password" class="form-control" minlength="8">
                    </div>
                    <div class="col">
                        <label class="form-label">التأكيد</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="8">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">الدور <span class="text-danger">*</span></label>
                    <select name="role" id="edit-role" class="form-select" onchange="toggleEditPerms(this.value)">
                        <option value="cashier">كاشير</option>
                        <option value="admin">مسؤول (Admin)</option>
                    </select>
                </div>
                <div id="edit-perms-div" class="mb-1">
                    <label class="form-label">الصلاحيات</label>
                    <?php foreach ($availablePermissions as $pKey => $pLabel): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               name="perm_<?= $pKey ?>" id="edit-perm-<?= $pKey ?>" value="1">
                        <label class="form-check-label" for="edit-perm-<?= $pKey ?>">
                            <?= htmlspecialchars($pLabel) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-warning"><i class="bi bi-save"></i> حفظ</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../views/partials/footer.php'; ?>

<script>
function toggleAddPerms(role)  { document.getElementById('add-perms-div').style.display  = role === 'admin' ? 'none' : ''; }
function toggleEditPerms(role) { document.getElementById('edit-perms-div').style.display = role === 'admin' ? 'none' : ''; }

document.querySelectorAll('.edit-user-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const perms = JSON.parse(btn.dataset.perms || '[]');
        document.getElementById('edit-user-id').value       = btn.dataset.id;
        document.getElementById('edit-user-username').value = btn.dataset.username;
        document.getElementById('edit-role').value          = btn.dataset.role;
        toggleEditPerms(btn.dataset.role);
        document.querySelectorAll('#edit-perms-div input[type=checkbox]').forEach(cb => {
            cb.checked = perms.includes(cb.name.replace('perm_', ''));
        });
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    });
});

<?php if ($error): ?>
document.addEventListener('DOMContentLoaded', () => {
    window.scrollTo(0, 0);
    <?php $postAction = $_POST['action'] ?? ''; ?>
    <?php if ($postAction === 'add'): ?>new bootstrap.Modal(document.getElementById('addUserModal')).show();<?php endif; ?>
    <?php if ($postAction === 'edit'): ?>new bootstrap.Modal(document.getElementById('editUserModal')).show();<?php endif; ?>
});
<?php endif; ?>
</script>
