<?php
namespace Middleware;

use Core\Auth;

class AuthMiddleware {
    public static function handle($permission = null) {
        Auth::requireLogin();
        if ($permission) {
            Auth::requirePermission($permission);
        }
        // CSRF Check for POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
            if (!\Core\Security::validateCSRFToken($token)) {
                http_response_code(403);
                die('CSRF validation failed');
            }
        }
    }
}
