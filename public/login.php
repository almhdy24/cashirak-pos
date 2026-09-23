<?php
require_once __DIR__ . '/../bootstrap.php';
use Core\Auth;
use Core\Security;

// Redirect if already logged in
if (Auth::check()) {
    header('Location: ' . (Auth::hasPermission('manage_items') ? 'admin.php' : 'index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('رمز الأمان غير صحيح');
    }

    $username = trim($_POST['username'] ?? '');

    // Rate limiting: max 5 attempts per username per 5 minutes (stored in session)
    $attemptKey = 'login_attempts_' . md5(strtolower($username));
    $attempts   = $_SESSION[$attemptKey] ?? ['count' => 0, 'lockout_until' => 0];

    if ($attempts['lockout_until'] > time()) {
        $waitMin = (int)ceil(($attempts['lockout_until'] - time()) / 60);
        $error = "تم تجاوز عدد المحاولات المسموح بها. يرجى الانتظار $waitMin دقيقة.";
    } elseif (Auth::login($username, $_POST['password'] ?? '')) {
        // Clear attempts on success
        unset($_SESSION[$attemptKey]);
        $redirect = Auth::hasPermission('manage_items') ? 'admin.php' : 'index.php';
        header("Location: $redirect");
        exit;
    } else {
        $attempts['count']++;
        if ($attempts['count'] >= 5) {
            $attempts['lockout_until'] = time() + 300; // 5 min
            $attempts['count']         = 0;
            $error = 'تم تجاوز عدد المحاولات. الحساب مقفل لمدة 5 دقائق.';
        } else {
            $remaining = 5 - $attempts['count'];
            $error = "بيانات الدخول غير صحيحة. تبقى $remaining محاولة.";
        }
        $_SESSION[$attemptKey] = $attempts;
    }
}

$csrf       = Security::generateCSRFToken();
$cafe_title = getSetting('cafe_title', 'كاشيراك');
$pageTitle  = "تسجيل الدخول - $cafe_title";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/logo.svg">
    <link rel="stylesheet" href="/assets/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="/assets/bootstrap-icons.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            background: linear-gradient(150deg, #0f172a 0%, #1e3a5f 60%, #0f172a 100%);
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; margin: 0;
            font-family: 'Tahoma', 'Segoe UI', system-ui, sans-serif;
        }
        .login-wrap {
            width: 100%; max-width: 420px;
            padding: 1rem;
        }
        .login-card {
            padding: 2.25rem 2rem 1.75rem;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 24px 64px rgba(0,0,0,.35);
            text-align: center;
        }
        .login-card .logo { margin-bottom: 1rem; }
        .login-card h1 { font-weight: 700; font-size: 1.7rem; color: #0f172a; margin-bottom: .25rem; }
        .login-card .sub { color: #64748b; margin-bottom: 1.75rem; font-size: .9rem; }
        .form-control {
            border-radius: 10px; padding: .65rem 1rem;
            border: 1.5px solid #e2e8f0;
            transition: border-color .15s, box-shadow .15s;
            font-family: inherit;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,.15);
            outline: none;
        }
        .input-group-text {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            color: #94a3b8;
        }
        .input-group .form-control { border-radius: 0 10px 10px 0; }
        .input-group .input-group-text { border-radius: 10px 0 0 10px; border-left: none; }
        [dir="rtl"] .input-group .form-control { border-radius: 10px 0 0 10px; }
        [dir="rtl"] .input-group .input-group-text { border-radius: 0 10px 10px 0; border-right: none; border-left: 1.5px solid #e2e8f0; }
        .btn-login {
            border-radius: 10px; padding: .72rem;
            font-weight: 700; font-size: .95rem;
            background: #2563eb; color: #fff;
            border: none; width: 100%;
            transition: background .15s, transform .1s;
            cursor: pointer;
        }
        .btn-login:hover  { background: #1d4ed8; }
        .btn-login:active { transform: scale(.98); }
        .alert {
            border-radius: 10px; font-size: .88rem;
            padding: .65rem 1rem; border: none;
            background: #fef2f2; color: #b91c1c;
            margin-bottom: 1.25rem;
        }
        .footer-login { margin-top: 1.5rem; font-size: .82rem; color: #94a3b8; }
        .footer-login a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
    <div class="login-wrap"><div class="login-card">
        <div class="logo"><img src="/assets/logo.svg" alt="كاشيراك" height="70"></div>
        <h1><?= htmlspecialchars($cafe_title) ?></h1>
        <p class="sub">نظام نقاط البيع</p>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-0">
                        <i class="bi bi-person-fill text-secondary"></i>
                    </span>
                    <input type="text" name="username" class="form-control"
                           placeholder="اسم المستخدم" required autofocus autocomplete="username">
                </div>
            </div>
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-0">
                        <i class="bi bi-lock-fill text-secondary"></i>
                    </span>
                    <input type="password" name="password" class="form-control"
                           placeholder="كلمة المرور" required autocomplete="current-password">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-login w-100">
                <i class="bi bi-box-arrow-in-right"></i> دخول
            </button>
        </form>

        <div class="footer-login">
            <small>© <?= date('Y') ?> <a href="https://almhdy24.com" target="_blank">Elmahdi Dev</a></small>
        </div>
    </div></div>
</body>
</html>
