<?php
namespace Models;

use Core\DB;

class Order {
    // Create new order with payment method
    public static function create($total, $shift_id, $cashier_id, $payment_method = 'cash') {
        $db = DB::conn();
        $stmt = $db->prepare("
            INSERT INTO orders (total, created_at, shift_id, cashier_id, status, payment_method) 
            VALUES (?, datetime('now','localtime'), ?, ?, 'active', ?)
        ");
        $stmt->execute([$total, $shift_id, $cashier_id, $payment_method]);
        return $db->lastInsertId();
    }
    
    // Get stats for a shift
    public static function getShiftStats($shift_id) {
        $stmt = DB::conn()->prepare("
            SELECT COUNT(*) as orders, SUM(total) as sales 
            FROM orders 
            WHERE shift_id = ? AND status = 'active'
        ");
        $stmt->execute([$shift_id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
