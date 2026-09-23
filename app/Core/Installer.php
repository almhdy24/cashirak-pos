<?php
namespace Core;

class Installer
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = DB::conn();
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function checkRequirements(): array
    {
        return [
            'php'     => version_compare(PHP_VERSION, '8.0.0', '>='),
            'sqlite'  => extension_loaded('pdo_sqlite'),
            'openssl' => extension_loaded('openssl'),
            'curl'    => extension_loaded('curl'),
            'storage' => is_writable(defined('STORAGE_PATH') ? STORAGE_PATH : __DIR__ . '/../../storage'),
        ];
    }

    public function validateInput(array $data): ?string
    {
        if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $data['admin_username'] ?? '')) {
            return 'اسم المستخدم يجب أن يكون 3-32 حرفاً (أحرف إنجليزية، أرقام، شرطة سفلية)';
        }
        if (strlen($data['admin_password'] ?? '') < 8) {
            return 'كلمة المرور يجب أن تكون 8 أحرف على الأقل';
        }
        if (($data['admin_password'] ?? '') !== ($data['admin_password_confirm'] ?? '')) {
            return 'كلمتا المرور غير متطابقتين';
        }
        $titleLen = mb_strlen($data['cafe_title'] ?? '', 'UTF-8');
        if ($titleLen < 2 || $titleLen > 100) {
            return 'اسم المحل يجب أن يكون بين 2 و 100 حرف';
        }
        $currLen = mb_strlen($data['currency'] ?? '', 'UTF-8');
        if ($currLen < 1 || $currLen > 10) {
            return 'رمز العملة يجب أن يكون 1-10 أحرف';
        }
        return null;
    }

    // Returns ['success' => bool, 'message' => string, 'cashier_password' => string]
    public function install(array $data): array
    {
        $error = $this->validateInput($data);
        if ($error) {
            return ['success' => false, 'message' => $error, 'cashier_password' => ''];
        }

        $this->db->beginTransaction();
        try {
            $this->createTables();

            $adminPass = Security::hashPassword($data['admin_password']);

            // Random 8-char cashier password shown once to admin
            $cashierPassPlain = strtolower(bin2hex(random_bytes(4)));
            $cashierPass      = Security::hashPassword($cashierPassPlain);

            $this->db->exec("DELETE FROM users");
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password, role, permissions) VALUES
                (?, ?, 'admin', '[]'),
                (?, ?, 'cashier', '[\"process_order\"]')
            ");
            $stmt->execute([$data['admin_username'], $adminPass, 'cashier', $cashierPass]);

            $this->seedCategories();
            $this->seedItems();
            $this->seedPaymentMethods();

            $settings = [
                'cafe_title'     => $data['cafe_title'],
                'currency'       => $data['currency'],
                'invoice_footer' => $data['invoice_footer'] ?? 'شكراً لزيارتكم',
                'date_format'    => $data['date_format']    ?? 'Y-m-d H:i:s',
            ];
            $setStmt = $this->db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
            foreach ($settings as $key => $value) {
                $setStmt->execute([$key, $value]);
            }

            $this->db->commit();

            $lockPath = (defined('STORAGE_PATH') ? STORAGE_PATH : __DIR__ . '/../../storage') . '/installed.lock';
            if (file_put_contents($lockPath, date('Y-m-d H:i:s'), LOCK_EX) === false) {
                return ['success' => false, 'message' => 'تم التثبيت لكن فشل كتابة ملف القفل.', 'cashier_password' => ''];
            }

            return ['success' => true, 'message' => '', 'cashier_password' => $cashierPassPlain];

        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'فشل التثبيت: ' . $e->getMessage(), 'cashier_password' => ''];
        }
    }

    private function createTables(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                username    TEXT UNIQUE NOT NULL,
                password    TEXT NOT NULL,
                role        TEXT NOT NULL CHECK(role IN ('admin','cashier')),
                permissions TEXT DEFAULT '[]'
            );

            CREATE TABLE IF NOT EXISTS categories (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT NOT NULL UNIQUE,
                description TEXT,
                created_at  TEXT DEFAULT (datetime('now','localtime'))
            );

            CREATE TABLE IF NOT EXISTS items (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT NOT NULL UNIQUE,
                barcode     TEXT,
                price       REAL NOT NULL,
                cost_price  REAL DEFAULT 0,
                stock       INTEGER DEFAULT NULL,
                category_id INTEGER DEFAULT NULL REFERENCES categories(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS payment_methods (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL,
                is_active  INTEGER DEFAULT 1,
                sort_order INTEGER DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS orders (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                total           REAL NOT NULL,
                created_at      TEXT NOT NULL,
                shift_id        INTEGER NOT NULL,
                cashier_id      INTEGER NOT NULL,
                status          TEXT DEFAULT 'active',
                cancelled_at    TEXT,
                payment_method  TEXT DEFAULT 'كاش',
                idempotency_key TEXT UNIQUE
            );

            CREATE TABLE IF NOT EXISTS order_items (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                name     TEXT NOT NULL,
                qty      INTEGER NOT NULL,
                price    REAL NOT NULL,
                subtotal REAL NOT NULL
            );

            CREATE TABLE IF NOT EXISTS shifts (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                start_time   TEXT NOT NULL,
                end_time     TEXT,
                total_sales  REAL    DEFAULT 0,
                total_orders INTEGER DEFAULT 0,
                status       TEXT NOT NULL,
                opened_by    INTEGER
            );

            CREATE TABLE IF NOT EXISTS audit_logs (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                action     TEXT NOT NULL,
                user_id    INTEGER,
                entity_id  INTEGER,
                details    TEXT,
                created_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS settings (
                key   TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );
        ");

        // Performance indexes
        $this->db->exec("
            CREATE INDEX IF NOT EXISTS idx_orders_shift_id   ON orders(shift_id);
            CREATE INDEX IF NOT EXISTS idx_orders_status     ON orders(status);
            CREATE INDEX IF NOT EXISTS idx_orders_idem_key   ON orders(idempotency_key);
            CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);
            CREATE INDEX IF NOT EXISTS idx_shifts_status     ON shifts(status);
            CREATE INDEX IF NOT EXISTS idx_items_barcode     ON items(barcode);
        ");
    }

    private function seedCategories(): void
    {
        $cats = [
            ['مشروبات ساخنة', 'شاي، قهوة، نسكافيه'],
            ['مشروبات باردة', 'عصائر، مياه، مشروبات غازية'],
            ['وجبات سريعة',   'ساندويشات، برجر'],
            ['حلويات',        'كيك، بسكويت، كريم كراميل'],
        ];
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO categories (name, description) VALUES (?, ?)");
        foreach ($cats as $c) $stmt->execute($c);
    }

    private function seedItems(): void
    {
        $items = [
            ['شاي سادة',     500,  'مشروبات ساخنة'],
            ['شاي بالحليب',  800,  'مشروبات ساخنة'],
            ['قهوة عربية',   1200, 'مشروبات ساخنة'],
            ['نسكافيه',      1500, 'مشروبات ساخنة'],
            ['ماء معدني',    600,  'مشروبات باردة'],
            ['عصير برتقال',  1500, 'مشروبات باردة'],
            ['عصير ليمون',   1200, 'مشروبات باردة'],
            ['مشروب غازي',   1000, 'مشروبات باردة'],
            ['فول',          2000, 'وجبات سريعة'],
            ['بيض',          2500, 'وجبات سريعة'],
            ['برجر',         4500, 'وجبات سريعة'],
            ['قطعة كيك',     1800, 'حلويات'],
            ['بسكويت',       800,  'حلويات'],
            ['كريم كراميل',  2000, 'حلويات'],
        ];
        $stmt = $this->db->prepare("
            INSERT OR IGNORE INTO items (name, price, category_id)
            VALUES (?, ?, (SELECT id FROM categories WHERE name = ?))
        ");
        foreach ($items as $i) $stmt->execute([$i[0], $i[1], $i[2]]);
    }

    private function seedPaymentMethods(): void
    {
        $methods = [
            ['كاش',          1, 1],
            ['بنكك',         1, 2],
            ['ماي كاشي',     1, 3],
            ['تحويل بنكي',   1, 4],
        ];
        // Only seed if table is empty
        $count = $this->db->query("SELECT COUNT(*) FROM payment_methods")->fetchColumn();
        if ($count > 0) return;

        $stmt = $this->db->prepare("INSERT INTO payment_methods (name, is_active, sort_order) VALUES (?, ?, ?)");
        foreach ($methods as $m) $stmt->execute($m);
    }
}
