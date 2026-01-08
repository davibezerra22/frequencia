<?php
namespace App\Support;
class Session {
    public static function start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $domain = Env::get('COOKIE_DOMAIN', '');
        $secure = false;
        $params = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => $domain ?: '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        session_name('frequencia_sess');
        session_set_cookie_params($params);
        session_start();
    }
}
