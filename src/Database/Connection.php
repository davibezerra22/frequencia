<?php
namespace App\Database;
use PDO;
use App\Config\Database;
class Connection {
    private static ?PDO $pdo = null;
    public static function get(): PDO {
        if (self::$pdo) return self::$pdo;
        $cfg = Database::get();
        $dsn = 'mysql:host='.$cfg['host'].';port='.$cfg['port'].';dbname='.$cfg['name'].';charset='.$cfg['charset'];
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        self::$pdo = $pdo;
        return self::$pdo;
    }
}
