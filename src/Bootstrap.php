<?php
use App\Support\Env;
use App\Database\Connection;
use App\Database\Ensure;
Env::load(__DIR__ . '/../.env');
Env::load(__DIR__ . '/../env.local');
date_default_timezone_set(Env::get('APP_TIMEZONE','America/Sao_Paulo'));
try {
  $pdo = Connection::get();
  Ensure::run($pdo);
} catch (\Throwable $e) {
  // ignore ensure errors during bootstrap
}
