<?php
namespace Services;

use Models\Shift;
use Models\Order;

class ShiftService {
    public static function closeShift($shift_id) {
        $stats = Order::getShiftStats($shift_id);
        Shift::close($shift_id, $stats['sales'] ?? 0, $stats['orders'] ?? 0);
        return $stats;
    }
}
