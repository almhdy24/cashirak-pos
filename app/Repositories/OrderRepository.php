<?php
namespace Repositories;

use Core\DB;
use Models\Order;
use Models\OrderItem;

class OrderRepository {
    public static function createWithItems($data, $shift_id, $cashier_id) {
        DB::beginTransaction();
        try {
            $order_id = Order::create($data['total'], $shift_id, $cashier_id);
            foreach ($data['items'] as $item) {
                OrderItem::add($order_id, $item['name'], $item['qty'], $item['price']);
            }
            self::logAudit('order_created', $cashier_id, $order_id, json_encode($data));
            DB::commit();
            return $order_id;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private static function logAudit($action, $user_id, $entity_id, $details) {
        $stmt = DB::conn()->prepare("INSERT INTO audit_logs (action, user_id, entity_id, details, created_at) VALUES (?, ?, ?, ?, datetime('now','localtime'))");
        $stmt->execute([$action, $user_id, $entity_id, $details]);
    }
}
