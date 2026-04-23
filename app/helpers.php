<?php
// Load bootstrap first
require_once __DIR__ . '/bootstrap.php';

// Start session using our Session class
\Core\Session::start();

// Initialize database schema (only creates tables if not exist)
function initDatabase() {
    $db = \Core\DB::conn();
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
}

// Initialize database (create tables only, no default data)
initDatabase();
