<?php
// Global helper functions

if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = ''): string
    {
        try {
            if (class_exists('Core\DB')) {
                $stmt = Core\DB::conn()->prepare("SELECT value FROM settings WHERE key = ?");
                $stmt->execute([$key]);
                $value = $stmt->fetchColumn();
                return $value !== false ? (string)$value : $default;
            }
        } catch (\Exception $e) {}
        return $default;
    }
}

// Run once per request to ensure schema is up-to-date (idempotent ALTER TABLE migrations)
function runMigrations(): void
{
    try {
        $db   = \Core\DB::conn();
        $cols = fn(string $t) => array_column(
            $db->query("PRAGMA table_info($t)")->fetchAll(\PDO::FETCH_ASSOC), 'name'
        );

        // orders table
        $orderCols = $cols('orders');
        if (!in_array('payment_method', $orderCols)) {
            $db->exec("ALTER TABLE orders ADD COLUMN payment_method TEXT DEFAULT 'كاش'");
        }
        if (!in_array('idempotency_key', $orderCols)) {
            $db->exec("ALTER TABLE orders ADD COLUMN idempotency_key TEXT");
            $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_idem_key ON orders(idempotency_key)");
        }

        // items table
        $itemCols = $cols('items');
        if (!in_array('barcode', $itemCols)) {
            $db->exec("ALTER TABLE items ADD COLUMN barcode TEXT");
            $db->exec("CREATE INDEX IF NOT EXISTS idx_items_barcode ON items(barcode)");
        }
        if (!in_array('stock', $itemCols)) {
            $db->exec("ALTER TABLE items ADD COLUMN stock INTEGER DEFAULT NULL");
        }
        if (!in_array('cost_price', $itemCols)) {
            $db->exec("ALTER TABLE items ADD COLUMN cost_price REAL DEFAULT 0");
        }
        if (!in_array('min_stock', $itemCols)) {
            $db->exec("ALTER TABLE items ADD COLUMN min_stock INTEGER DEFAULT NULL");
        }

        // shifts table — add reconciliation columns
        $shiftCols = $cols('shifts');
        if (!in_array('opening_cash', $shiftCols)) {
            $db->exec("ALTER TABLE shifts ADD COLUMN opening_cash REAL DEFAULT 0");
        }
        if (!in_array('actual_cash', $shiftCols)) {
            $db->exec("ALTER TABLE shifts ADD COLUMN actual_cash REAL DEFAULT NULL");
        }
        if (!in_array('close_note', $shiftCols)) {
            $db->exec("ALTER TABLE shifts ADD COLUMN close_note TEXT DEFAULT ''");
        }

        // payment_methods table
        $tables = array_column(
            $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_ASSOC), 'name'
        );
        if (!in_array('payment_methods', $tables)) {
            $db->exec("
                CREATE TABLE payment_methods (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    name       TEXT NOT NULL,
                    is_active  INTEGER DEFAULT 1,
                    sort_order INTEGER DEFAULT 0
                )
            ");
            $db->exec("
                INSERT INTO payment_methods (name, is_active, sort_order) VALUES
                ('كاش', 1, 1), ('بنكك', 1, 2), ('ماي كاشي', 1, 3), ('تحويل بنكي', 1, 4)
            ");
        }

        // expenses table
        if (!in_array('expenses', $tables)) {
            $db->exec("
                CREATE TABLE expenses (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    shift_id    INTEGER NOT NULL,
                    user_id     INTEGER NOT NULL,
                    amount      REAL NOT NULL,
                    description TEXT NOT NULL,
                    created_at  TEXT DEFAULT (datetime('now','localtime'))
                )
            ");
        }

        // returns table
        if (!in_array('returns', $tables)) {
            $db->exec("
                CREATE TABLE returns (
                    id             INTEGER PRIMARY KEY AUTOINCREMENT,
                    order_id       INTEGER NOT NULL,
                    user_id        INTEGER NOT NULL,
                    shift_id       INTEGER NOT NULL,
                    total_refund   REAL NOT NULL,
                    reason         TEXT DEFAULT '',
                    payment_method TEXT DEFAULT 'كاش',
                    created_at     TEXT DEFAULT (datetime('now','localtime'))
                )
            ");
        }

        // return_items table
        if (!in_array('return_items', $tables)) {
            $db->exec("
                CREATE TABLE return_items (
                    id        INTEGER PRIMARY KEY AUTOINCREMENT,
                    return_id INTEGER NOT NULL,
                    name      TEXT NOT NULL,
                    qty       INTEGER NOT NULL,
                    price     REAL NOT NULL,
                    subtotal  REAL NOT NULL,
                    item_id   INTEGER DEFAULT NULL
                )
            ");
        }

        // Settings defaults
        $stmt = $db->query("SELECT COUNT(*) FROM settings");
        if ($stmt->fetchColumn() == 0) {
            $db->exec("
                INSERT INTO settings (key, value) VALUES
                ('cafe_title',     'كاشيراك'),
                ('invoice_footer', 'شكراً لزيارتكم'),
                ('date_format',    'Y-m-d H:i:s'),
                ('currency',       'SDG')
            ");
        }

        // Indexes
        $db->exec("
            CREATE INDEX IF NOT EXISTS idx_orders_shift_id   ON orders(shift_id);
            CREATE INDEX IF NOT EXISTS idx_orders_status     ON orders(status);
            CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);
            CREATE INDEX IF NOT EXISTS idx_shifts_status     ON shifts(status);
            CREATE INDEX IF NOT EXISTS idx_expenses_shift    ON expenses(shift_id);
            CREATE INDEX IF NOT EXISTS idx_returns_order     ON returns(order_id);
            CREATE INDEX IF NOT EXISTS idx_returns_shift     ON returns(shift_id);
        ");

    } catch (\Exception $e) {
        error_log("Migration error: " . $e->getMessage());
    }
}

runMigrations();
