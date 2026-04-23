<?php
require_once __DIR__.'/../app/helpers.php';
use Core\Auth;

echo "<h1>فحص الجلسة</h1>";
echo "Session ID: " . session_id() . "<br>";
echo "Auth::check(): " . (Auth::check() ? 'true' : 'false') . "<br>";
if (Auth::check()) {
    $user = Auth::user();
    echo "User: " . htmlspecialchars($user['username']) . " (" . $user['role'] . ")<br>";
    echo "Permissions: " . htmlspecialchars($user['permissions']) . "<br>";
    echo "Has 'process_order': " . (Auth::hasPermission('process_order') ? 'true' : 'false') . "<br>";
} else {
    echo "<br>لم يتم تسجيل الدخول.";
}
echo "<br><br><a href='login.php'>تسجيل الدخول</a> | <a href='logout.php'>خروج</a>";
