<?php
namespace App\Support;
class Env {
    public static function load(string $path): void {
        if (!is_file($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(ltrim($line), '#') === 0) continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $value = trim($value, "\"'");
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
    public static function get(string $key, $default=null) {
        $v = $_ENV[$key] ?? getenv($key);
        return $v !== false && $v !== null ? $v : $default;
    }
}
