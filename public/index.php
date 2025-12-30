<?php
require_once __DIR__ . '/../src/Support/Env.php';
require_once __DIR__ . '/../src/Config/Database.php';
require_once __DIR__ . '/../src/Database/Connection.php';
require_once __DIR__ . '/../src/Bootstrap.php';
use App\Database\Connection;
header('Content-Type: application/json; charset=utf-8');
try {
    $pdo = Connection::get();
    echo json_encode(['status' => 'ok']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
