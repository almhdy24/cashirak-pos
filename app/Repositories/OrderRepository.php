<?php
namespace Repositories;

use Core\DB;
use Models\OrderItem;
use Models\Item;

class OrderRepository
{
    public static function createWithItems(array $data, int $shift_id, int $cashier_id): int
    {
        DB::beginTransaction();
        try {
            $payment_method = (string)($data['payment_method'] ?? 'كاش');
            $idemKey        = isset($data['idempotency_key']) && $data['idempotency_key'] !== ''
                              ? (string)$data['idempotency_key'] : null;

            // Recalculate total server-side when item_ids are provided (prevents price tampering)
            $total          = 0.0;
            $allHaveIds     = !empty($data['items']) && array_reduce(
                $data['items'], fn($carry, $i) => $carry && !empty($i['item_id']), true
            );
            foreach ($data['items'] as $item) {
                $qty = max(1, (int)$item['qty']);
                if ($allHaveIds) {
                    $dbItem = Item::find((int)$item['item_id']);
                    if (!$dbItem) throw new \Exception('صنف غير موجود: ' . ($item['name'] ?? ''));
                    $total += $dbItem['price'] * $qty;
                } else {
                    $total += (float)$item['price'] * $qty;
                }
            }

            $db = DB::conn();
            $stmt = $db->prepare("
                INSERT INTO orders (total, created_at, shift_id, cashier_id, status, payment_method, idempotency_key)
                VALUES (?, datetime('now','localtime'), ?, ?, 'active', ?, ?)
            ");
            $stmt->execute([$total, $shift_id, $cashier_id, $payment_method, $idemKey]);
            $order_id = (int)$db->lastInsertId();

            foreach ($data['items'] as $item) {
                $qty  = max(1, (int)$item['qty']);
                $name = (string)($item['name'] ?? '');
                $price = $allHaveIds
                    ? Item::find((int)$item['item_id'])['price']
                    : (float)$item['price'];
                OrderItem::add($order_id, $name, $qty, $price);
                if (!empty($item['item_id'])) {
                    Item::deductStock((int)$item['item_id'], $qty);
                }
            }

            self::logAudit('order_created', $cashier_id, $order_id, json_encode([
                'total'          => $total,
                'payment_method' => $payment_method,
                'items_count'    => count($data['items']),
            ]));

            DB::commit();
            return $order_id;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private static function logAudit(string $action, int $user_id, int $entity_id, string $details): void
    {
        DB::conn()->prepare("
            INSERT INTO audit_logs (action, user_id, entity_id, details, created_at)
            VALUES (?, ?, ?, ?, datetime('now','localtime'))
        ")->execute([$action, $user_id, $entity_id, $details]);
    }
}
