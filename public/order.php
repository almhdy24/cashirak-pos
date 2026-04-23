<?php
require_once __DIR__.'/../app/helpers.php';

use Middleware\AuthMiddleware;
use Services\OrderService;
use Models\Shift;
use Core\Auth;

AuthMiddleware::handle('process_order');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['items']) || !isset($data['total']) || !isset($data['shift_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'بيانات الطلب غير مكتملة']);
    exit;
}

$user = Auth::user();
$shift_id = (int)$data['shift_id'];

try {
    $order_id = OrderService::processOrder($data, $shift_id, $user['id']);
    echo json_encode([
        'status' => 'success',
        'order_id' => $order_id,
        'message' => 'تم حفظ الطلب بنجاح'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
