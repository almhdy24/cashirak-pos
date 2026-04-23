<?php
namespace Services;

use Repositories\OrderRepository;
use Core\DB;
use Models\Order;

class OrderService {
    public static function processOrder($orderData, $shift_id, $cashier_id) {
        return OrderRepository::createWithItems($orderData, $shift_id, $cashier_id);
    }

    public static function cancelOrder($order_id, $cashier_id) {
        $db = DB::conn();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND status = 'active'");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$order) {
                throw new \Exception("الطلب غير موجود أو ملغي مسبقاً");
            }

            $stmt = $db->prepare("UPDATE orders SET status = 'cancelled', cancelled_at = datetime('now','localtime') WHERE id = ?");
            $stmt->execute([$order_id]);

            $stmt = $db->prepare("INSERT INTO audit_logs (action, user_id, entity_id, details, created_at) VALUES (?, ?, ?, ?, datetime('now','localtime'))");
            $stmt->execute(['order_cancelled', $cashier_id, $order_id, json_encode(['previous_total' => $order['total']])]);

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
