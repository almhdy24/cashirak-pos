<?php
namespace Models;

use Core\DB;

class Order {
    public static function create($total, $shift_id, $cashier_id) {
        $db = DB::conn();
        $stmt = $db->prepare("INSERT INTO orders (total, created_at, shift_id, cashier_id, status) VALUES (?, datetime('now','localtime'), ?, ?, 'active')");
        $stmt->execute([$total, $shift_id, $cashier_id]);
        return $db->lastInsertId();
    }

    public static function getShiftStats($shift_id) {
        $stmt = DB::conn()->prepare("SELECT COUNT(*) as orders, SUM(total) as sales FROM orders WHERE shift_id = ? AND status = 'active'");
        $stmt->execute([$shift_id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
