<?php
namespace Models;

use Core\DB;

class Item
{
    public static function all(): array
    {
        return DB::conn()->query("
            SELECT i.*, c.name AS category_name
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
            ORDER BY i.name
        ")->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function allByCategory(?int $category_id = null): array
    {
        $sql = "SELECT i.*, c.name AS category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id";
        if ($category_id) {
            $stmt = DB::conn()->prepare($sql . " WHERE i.category_id = ? ORDER BY i.name");
            $stmt->execute([$category_id]);
        } else {
            $stmt = DB::conn()->query($sql . " ORDER BY i.name");
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function find(int $id): array|false
    {
        $stmt = DB::conn()->prepare("SELECT * FROM items WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function findByBarcode(string $barcode): array|false
    {
        if ($barcode === '') return false;
        $stmt = DB::conn()->prepare("SELECT * FROM items WHERE barcode = ? LIMIT 1");
        $stmt->execute([$barcode]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function add(
        string $name,
        float  $price,
        ?int   $category_id = null,
        string $barcode      = '',
        ?int   $stock        = null,
        float  $cost_price   = 0.0
    ): bool {
        $stmt = DB::conn()->prepare("
            INSERT OR IGNORE INTO items (name, barcode, price, cost_price, stock, category_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $name,
            $barcode !== '' ? $barcode : null,
            $price,
            $cost_price,
            $stock,
            $category_id,
        ]);
    }

    public static function update(
        int    $id,
        string $name,
        float  $price,
        ?int   $category_id = null,
        string $barcode      = '',
        ?int   $stock        = null,
        float  $cost_price   = 0.0
    ): bool {
        $stmt = DB::conn()->prepare("
            UPDATE items
            SET name = ?, barcode = ?, price = ?, cost_price = ?, stock = ?, category_id = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $name,
            $barcode !== '' ? $barcode : null,
            $price,
            $cost_price,
            $stock,
            $category_id,
            $id,
        ]);
    }

    public static function delete(int $id): bool
    {
        $stmt = DB::conn()->prepare("DELETE FROM items WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function deductStock(int $item_id, int $qty): void
    {
        DB::conn()->prepare("
            UPDATE items SET stock = stock - ?
            WHERE id = ? AND stock IS NOT NULL AND stock >= ?
        ")->execute([$qty, $item_id, $qty]);
    }
}
