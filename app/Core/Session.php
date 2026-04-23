<?php
namespace Core;

class Session {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            // مسار مخصص لحفظ الجلسات داخل المشروع (يعمل على Termux)
            $sessionPath = __DIR__ . '/../../storage/sessions';
            if (!is_dir($sessionPath)) {
                mkdir($sessionPath, 0777, true);
            }
            session_save_path($sessionPath);
            session_start();
        }
        if (!isset($_SESSION['last_regeneration'])) {
            self::regenerate();
        } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
            self::regenerate();
        }
    }

    public static function regenerate() {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }

    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public static function get($key) {
        return $_SESSION[$key] ?? null;
    }

    public static function destroy() {
        session_destroy();
    }
}
