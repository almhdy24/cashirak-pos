<?php
// POST /order.php — create an order (AJAX endpoint)
require_once __DIR__ . '/../bootstrap.php';

use Middleware\AuthMiddleware;
use Services\OrderService;
use Core\Auth;
use Core\DB;

AuthMiddleware::handle('process_order');

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['items']) || !isset($data['total']) || empty($data['shift_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'بيانات الطلب غير مكتملة']);
    exit;
}

$user     = Auth::user();
$shift_id = (int)$data['shift_id'];

// Idempotency: if we've seen this key before, return the existing order
$idemKey = trim((string)($data['idempotency_key'] ?? ''));
if ($idemKey !== '') {
    try {
        $existing = DB::conn()
            ->prepare("SELECT id FROM orders WHERE idempotency_key = ? LIMIT 1");
        $existing->execute([$idemKey]);
        $row = $existing->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            echo json_encode([
                'status'    => 'success',
                'order_id'  => (int)$row['id'],
                'duplicate' => true,
            ]);
            exit;
        }
    } catch (\Throwable $e) {
        // If column doesn't exist yet (old DB), just proceed
    }
}

try {
    $order_id = OrderService::processOrder($data, $shift_id, $user['id']);
    echo json_encode([
        'status'   => 'success',
        'order_id' => $order_id,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'فشل حفظ الطلب']);
    error_log('order.php error: ' . $e->getMessage());
}
