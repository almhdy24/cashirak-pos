<?php
namespace Core;

// Simple key-value store for settings
class Settings {
    public static function get($key, $default = '') {
        $db = DB::conn();
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    }
    
    public static function set($key, $value) {
        $db = DB::conn();
        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
        return $stmt->execute([$key, $value]);
    }
    
    public static function getAll() {
        $db = DB::conn();
        $stmt = $db->query("SELECT key, value FROM settings");
        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }
}
