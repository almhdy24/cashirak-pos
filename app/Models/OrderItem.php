<?php
namespace Models;

use Core\DB;

class OrderItem {
    public static function add($order_id, $name, $qty, $price) {
        $stmt = DB::conn()->prepare("INSERT INTO order_items (order_id, name, qty, price, subtotal) VALUES (?,?,?,?,?)");
        $stmt->execute([$order_id, $name, $qty, $price, $qty*$price]);
    }

    public static function bestSellers($shift_id, $limit = 5) {
        $stmt = DB::conn()->prepare("
            SELECT name, SUM(qty) as sold
            FROM order_items
            WHERE order_id IN (SELECT id FROM orders WHERE shift_id = ?)
            GROUP BY name ORDER BY sold DESC LIMIT ?
        ");
        $stmt->execute([$shift_id, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
