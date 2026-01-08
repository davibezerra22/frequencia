<?php
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Database/Ensure.php';
header('Content-Type: application/json; charset=utf-8');
try {
  $pdo = \App\Database\Connection::get();
  \App\Database\Ensure::run($pdo);
  $cols = $pdo->query("SHOW COLUMNS FROM alunos LIKE 'codigo_curto'")->fetch();
  echo json_encode(['status'=>'ok','has_codigo_curto'=> (bool)$cols]);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
