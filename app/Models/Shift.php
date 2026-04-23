<?php
namespace Models;

use Core\DB;

class Shift {
    public static function getActive() {
        $db = DB::conn();
        $stmt = $db->query("SELECT * FROM shifts WHERE status='open' ORDER BY id DESC LIMIT 1");
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function open($cashier_id) {
        $db = DB::conn();
        $stmt = $db->prepare("INSERT INTO shifts (start_time, status, opened_by) VALUES (datetime('now','localtime'), 'open', ?)");
        $stmt->execute([$cashier_id]);
        return $db->lastInsertId();
    }

    public static function getActiveOrOpen($cashier_id) {
        $active = self::getActive();
        if ($active) return $active['id'];
        return self::open($cashier_id);
    }

    public static function close($shift_id, $total_sales, $total_orders) {
        $db = DB::conn();
        $stmt = $db->prepare("UPDATE shifts SET end_time=datetime('now','localtime'), total_sales=?, total_orders=?, status='closed' WHERE id=?");
        return $stmt->execute([$total_sales, $total_orders, $shift_id]);
    }
}
