<?php
namespace App\Config;
use App\Support\Env;
class Database {
    public static function get(): array {
        return [
            'host' => Env::get('DB_HOST','127.0.0.1'),
            'port' => (int)Env::get('DB_PORT','3308'),
            'name' => Env::get('DB_NAME','frequencia'),
            'user' => Env::get('DB_USER','root'),
            'pass' => Env::get('DB_PASS',''),
            'charset' => Env::get('DB_CHARSET','utf8mb4'),
        ];
    }
}
