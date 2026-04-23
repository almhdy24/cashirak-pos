<?php
namespace Core;

class DB {
    private static $instance = null;

    public static function conn(): \PDO {
        if (self::$instance === null) {
            if (!is_dir(__DIR__.'/../../database')) mkdir(__DIR__.'/../../database', 0777, true);
            self::$instance = new \PDO('sqlite:'.__DIR__.'/../../database/cashirak.sqlite');
            self::$instance->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            self::$instance->exec("PRAGMA foreign_keys = ON;");
        }
        return self::$instance;
    }

    public static function beginTransaction() { self::conn()->beginTransaction(); }
    public static function commit() { self::conn()->commit(); }
    public static function rollBack() { self::conn()->rollBack(); }
}
