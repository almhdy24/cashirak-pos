<?php
require_once __DIR__.'/app/Core/DB.php';
require_once __DIR__.'/app/Core/Security.php';

use Core\DB;
use Core\Security;

echo "Starting Cashirak POS installation...\n";

$db = DB::conn();

echo "Creating tables...\n";

$db->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('admin','cashier')),
    permissions TEXT DEFAULT '[]'
);

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    created_at TEXT DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    price REAL NOT NULL,
    category_id INTEGER DEFAULT NULL REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    total REAL NOT NULL,
    created_at TEXT NOT NULL,
    shift_id INTEGER NOT NULL,
    cashier_id INTEGER NOT NULL,
    status TEXT DEFAULT 'active',
    cancelled_at TEXT
);

CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    qty INTEGER NOT NULL,
    price REAL NOT NULL,
    subtotal REAL NOT NULL
);

CREATE TABLE IF NOT EXISTS shifts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    start_time TEXT NOT NULL,
    end_time TEXT,
    total_sales REAL DEFAULT 0,
    total_orders INTEGER DEFAULT 0,
    status TEXT NOT NULL,
    opened_by INTEGER
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action TEXT NOT NULL,
    user_id INTEGER,
    entity_id INTEGER,
    details TEXT,
    created_at TEXT NOT NULL
);
");

echo "Adding default users...\n";

$check = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();

if ($check == 0) {
    $adminPass = Security::hashPassword('admin123');
    $cashierPass = Security::hashPassword('cashier123');

    $db->exec("
        INSERT INTO users (username, password, role, permissions) VALUES 
        ('admin', '$adminPass', 'admin', '[]'),
        ('cashier', '$cashierPass', 'cashier', '[\"process_order\"]')
    ");

    echo "  Admin   : admin / admin123\n";
    echo "  Cashier : cashier / cashier123\n";
}

echo "Adding default categories...\n";

$db->exec("
INSERT OR IGNORE INTO categories (name, description) VALUES 
('Hot Drinks', 'Tea, Coffee'),
('Cold Drinks', 'Juices, Water'),
('Fast Food', 'Sandwiches'),
('Desserts', 'Cakes, Biscuits');
");

echo "Adding default items...\n";

$db->exec("
INSERT OR IGNORE INTO items (name, price, category_id) VALUES 
('Plain Tea', 500, (SELECT id FROM categories WHERE name='Hot Drinks')),
('Milk Tea', 800, (SELECT id FROM categories WHERE name='Hot Drinks')),
('Arabic Coffee', 1200, (SELECT id FROM categories WHERE name='Hot Drinks')),
('Nescafe', 1500, (SELECT id FROM categories WHERE name='Hot Drinks')),
('Mineral Water', 600, (SELECT id FROM categories WHERE name='Cold Drinks')),
('Orange Juice', 1500, (SELECT id FROM categories WHERE name='Cold Drinks')),
('Lemon Juice', 1200, (SELECT id FROM categories WHERE name='Cold Drinks')),
('Soft Drink', 1000, (SELECT id FROM categories WHERE name='Cold Drinks')),
('Foul Sandwich', 2000, (SELECT id FROM categories WHERE name='Fast Food')),
('Egg Sandwich', 2500, (SELECT id FROM categories WHERE name='Fast Food')),
('Burger', 4500, (SELECT id FROM categories WHERE name='Fast Food')),
('Cake Slice', 1800, (SELECT id FROM categories WHERE name='Desserts')),
('Biscuit', 800, (SELECT id FROM categories WHERE name='Desserts')),
('Cream Caramel', 2000, (SELECT id FROM categories WHERE name='Desserts'));
");

echo "\nInstallation completed successfully!\n\n";
echo "Login credentials:\n";
echo "------------------\n";
echo "Admin   : admin / admin123\n";
echo "Cashier : cashier / cashier123\n\n";
echo "Start the server:\n";
echo "php -S localhost:8000 -t public\n";
