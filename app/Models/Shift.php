<?php
namespace Models;

use Core\DB;

class Shift {
    // Get currently active shift (status = 'open')
    public static function getActive() {
        $db = DB::conn();
        $stmt = $db->query("SELECT * FROM shifts WHERE status='open' ORDER BY id DESC LIMIT 1");
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // Open a new shift
    public static function open($cashier_id) {
        $db = DB::conn();
        $stmt = $db->prepare("INSERT INTO shifts (start_time, status, opened_by) VALUES (datetime('now','localtime'), 'open', ?)");
        $stmt->execute([$cashier_id]);
        return $db->lastInsertId();
    }

    // Get active shift ID, or open a new one
    public static function getActiveOrOpen($cashier_id) {
        $active = self::getActive();
        if ($active) return $active['id'];
        return self::open($cashier_id);
    }

    // Close a shift with final stats
    public static function close($shift_id, $total_sales, $total_orders) {
        $db = DB::conn();
        $stmt = $db->prepare("UPDATE shifts SET end_time=datetime('now','localtime'), total_sales=?, total_orders=?, status='closed' WHERE id=?");
        return $stmt->execute([$total_sales, $total_orders, $shift_id]);
    }

    // Set opening cash (called when cashier acknowledges opening amount)
    public static function setOpeningCash(int $shift_id, float $amount): void
    {
        DB::conn()->prepare("UPDATE shifts SET opening_cash = ? WHERE id = ?")
            ->execute([$amount, $shift_id]);
    }

    // Close with reconciliation data
    public static function closeWithReconciliation(int $shift_id, float $actual_cash, string $note, float $total_sales, int $total_orders): void
    {
        DB::conn()->prepare("
            UPDATE shifts
            SET end_time     = datetime('now','localtime'),
                total_sales  = ?,
                total_orders = ?,
                actual_cash  = ?,
                close_note   = ?,
                status       = 'closed'
            WHERE id = ?
        ")->execute([$total_sales, $total_orders, $actual_cash, $note, $shift_id]);
    }

    // Get all closed shifts, ordered by end time (newest first)
    public static function getAllClosed($limit = 50) {
        $db = DB::conn();
        $stmt = $db->prepare("
            SELECT s.*, u.username as opened_by_name,
                   s.opening_cash, s.actual_cash, s.close_note
            FROM shifts s
            LEFT JOIN users u ON s.opened_by = u.id
            WHERE s.status = 'closed'
            ORDER BY s.end_time DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Get a single shift by ID (including its orders stats)
    public static function getById($shift_id) {
        $db = DB::conn();
        $stmt = $db->prepare("
            SELECT s.*, u.username as opened_by_name 
            FROM shifts s
            LEFT JOIN users u ON s.opened_by = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$shift_id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // Get shift detailed statistics (best sellers, total orders, etc.)
    public static function getDetailedStats($shift_id) {
        $db = DB::conn();
        // Get orders summary
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_orders, SUM(total) as total_sales
            FROM orders WHERE shift_id = ? AND status = 'active'
        ");
        $stmt->execute([$shift_id]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Get total expenses for this shift
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE shift_id = ?");
        $stmt->execute([$shift_id]);
        $total_expenses = (float)$stmt->fetchColumn();

        // Get sum of cash refunds for this shift
        $stmt = $db->prepare("SELECT COALESCE(SUM(total_refund),0) FROM returns WHERE shift_id = ? AND payment_method = 'كاش'");
        $stmt->execute([$shift_id]);
        $cash_refunds = (float)$stmt->fetchColumn();

        // Get best selling items for this shift
        $stmt = $db->prepare("
            SELECT name, SUM(qty) as sold
            FROM order_items
            WHERE order_id IN (SELECT id FROM orders WHERE shift_id = ?)
            GROUP BY name ORDER BY sold DESC LIMIT 5
        ");
        $stmt->execute([$shift_id]);
        $best = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'total_orders'    => $stats['total_orders'] ?? 0,
            'total_sales'     => $stats['total_sales'] ?? 0,
            'best_sellers'    => $best,
            'total_expenses'  => $total_expenses,
            'cash_refunds'    => $cash_refunds,
        ];
    }
}
