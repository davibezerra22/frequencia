<?php
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
header('Content-Type: application/json; charset=utf-8');
try {
  $pdo = \App\Database\Connection::get();
  $cols = $pdo->query("SHOW COLUMNS FROM frequencias")->fetchAll();
  echo json_encode(['status'=>'ok','columns'=>$cols]);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
