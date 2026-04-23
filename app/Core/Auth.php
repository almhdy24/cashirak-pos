<?php
namespace Core;

use Models\User;

class Auth {
    public static function login($username, $password): bool {
        $user = User::findByUsername($username);
        if (!$user) {
            error_log("Auth: User '$username' not found.");
            return false;
        }
        
        if (!Security::verifyPassword($password, $user['password'])) {
            error_log("Auth: Password verification failed for user '$username'.");
            return false;
        }
        
        Session::regenerate();
        Session::set('user', $user);
        return true;
    }

    public static function check(): bool {
        return Session::get('user') !== null;
    }

    public static function user(): ?array {
        return Session::get('user');
    }

    public static function logout() {
        Session::destroy();
    }

    public static function hasPermission($permission): bool {
        $user = self::user();
        if (!$user) return false;
        if ($user['role'] === 'admin') return true;
        $permissions = json_decode($user['permissions'] ?? '[]', true);
        return in_array($permission, $permissions);
    }

    public static function requireLogin() {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function requirePermission($permission) {
        self::requireLogin();
        if (!self::hasPermission($permission)) {
            header('Location: index.php');
            exit;
        }
    }
}
