<?php
namespace Models;

use Core\DB;

class Category {
    public static function all() {
        $stmt = DB::conn()->query("SELECT * FROM categories ORDER BY name");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function find($id) {
        $stmt = DB::conn()->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function create($name, $description = '') {
        $stmt = DB::conn()->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        return $stmt->execute([$name, $description]);
    }

    public static function update($id, $name, $description) {
        $stmt = DB::conn()->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
        return $stmt->execute([$name, $description, $id]);
    }

    public static function delete($id) {
        // سيتم تعيين category_id = NULL تلقائياً للأصناف المرتبطة بسبب ON DELETE SET NULL
        $stmt = DB::conn()->prepare("DELETE FROM categories WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getItemsCount($category_id) {
        $stmt = DB::conn()->prepare("SELECT COUNT(*) FROM items WHERE category_id = ?");
        $stmt->execute([$category_id]);
        return $stmt->fetchColumn();
    }
}
