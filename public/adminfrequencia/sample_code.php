<?php
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
  $pdo = \App\Database\Connection::get();
  $row = $pdo->query("SELECT codigo_curto FROM alunos WHERE codigo_curto IS NOT NULL LIMIT 1")->fetch();
  echo json_encode(['status'=>'ok','codigo'=>$row['codigo_curto'] ?? null]);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
