<?php
namespace Models;

use Core\DB;

class Item {
    public static function all() {
        return DB::conn()->query("
            SELECT i.*, c.name as category_name 
            FROM items i 
            LEFT JOIN categories c ON i.category_id = c.id 
            ORDER BY i.name
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function allByCategory($category_id = null) {
        $sql = "SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id";
        if ($category_id) {
            $stmt = DB::conn()->prepare($sql . " WHERE i.category_id = ? ORDER BY i.name");
            $stmt->execute([$category_id]);
        } else {
            $stmt = DB::conn()->query($sql . " ORDER BY i.name");
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function find($id) {
        $stmt = DB::conn()->prepare("SELECT * FROM items WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function add($name, $price, $category_id = null) {
        $stmt = DB::conn()->prepare("INSERT OR IGNORE INTO items (name, price, category_id) VALUES (?, ?, ?)");
        return $stmt->execute([$name, $price, $category_id]);
    }

    public static function update($id, $name, $price, $category_id = null) {
        $stmt = DB::conn()->prepare("UPDATE items SET name = ?, price = ?, category_id = ? WHERE id = ?");
        return $stmt->execute([$name, $price, $category_id, $id]);
    }

    public static function delete($id) {
        $stmt = DB::conn()->prepare("DELETE FROM items WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
