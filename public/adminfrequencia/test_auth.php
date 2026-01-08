<?php
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
use App\Database\Connection;
header('Content-Type: application/json; charset=utf-8');
try {
  $pdo = Connection::get();
  $u = $pdo->query("SELECT id, usuario FROM usuarios LIMIT 5")->fetchAll();
  echo json_encode(['status'=>'ok','usuarios'=>$u]);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
