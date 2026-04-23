<?php
namespace Models;

use Core\DB;

class User {
    public static function findByUsername($username) {
        $stmt = DB::conn()->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function create($username, $password, $role, $permissions = []) {
        $stmt = DB::conn()->prepare("INSERT INTO users (username, password, role, permissions) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$username, $password, $role, json_encode($permissions)]);
    }
}
