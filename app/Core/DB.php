<?php
namespace Core;

class DB {
    private static $instance = null;

    public static function conn(): \PDO {
        if (self::$instance === null) {
            // Use DB_PATH constant when available (set by bootstrap or paths.php).
            // Fall back to relative path only if neither has loaded yet (e.g. direct script).
            $dbPath = defined('DB_PATH') ? DB_PATH : __DIR__ . '/../../database/cashirak.sqlite';
            $dbDir  = dirname($dbPath);
            if (!is_dir($dbDir)) mkdir($dbDir, 0777, true);
            self::$instance = new \PDO('sqlite:' . $dbPath);
            self::$instance->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            self::$instance->exec("PRAGMA foreign_keys = ON;");
        }
        return self::$instance;
    }

    public static function beginTransaction() { self::conn()->beginTransaction(); }
    public static function commit() { self::conn()->commit(); }
    public static function rollBack() { self::conn()->rollBack(); }
}
