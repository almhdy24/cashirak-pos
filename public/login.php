<?php
require_once __DIR__.'/../app/helpers.php';
use Core\Auth;
use Core\Security;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    if (Auth::login($_POST['username'], $_POST['password'])) {
        $redirect = Auth::hasPermission('manage_items') ? 'admin.php' : 'index.php';
        header("Location: $redirect");
        exit;
    } else {
        $error = 'بيانات الدخول غير صحيحة';
    }
}
$csrf = Security::generateCSRFToken();
$pageTitle = 'تسجيل الدخول - Cashirak V2';
include __DIR__.'/../views/partials/header.php';
?>

<style>
    body { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .login-card { width: 100%; max-width: 420px; padding: 2rem; border-radius: 1.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.1); background: white; }
</style>

<div class="login-card">
    <h3 class="text-center mb-4"><i class="bi bi-cash-coin fs-1 text-primary"></i><br>Cashirak POS</h3>
    <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-person"></i> المستخدم</label>
            <input type="text" name="username" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-lock"></i> كلمة المرور</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right"></i> دخول</button>
    </form>
    
</div>

<?php include __DIR__.'/../views/partials/footer.php'; ?>
