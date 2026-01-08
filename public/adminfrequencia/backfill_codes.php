<?php
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Support/ShortCode.php';
header('Content-Type: application/json; charset=utf-8');
try {
  $pdo = \App\Database\Connection::get();
  $secret = \App\Support\Env::get('QR_SECRET','dev-secret');
  $stmt = $pdo->query("SELECT id, escola_id, matricula FROM alunos WHERE matricula IS NOT NULL AND matricula <> ''");
  $rows = $stmt ? $stmt->fetchAll() : [];
  $updated = 0;
  foreach ($rows as $r) {
    $id = (int)$r['id']; $eid = (int)$r['escola_id']; $mat = (int)$r['matricula'];
    if ($mat>0) {
      $code = \App\Support\ShortCode::makeCode($eid, $mat, $secret);
      $ok = $pdo->prepare("UPDATE alunos SET codigo_curto=? WHERE id=?")->execute([$code, $id]);
      if ($ok) { $updated++; }
    }
  }
  echo json_encode(['status'=>'ok','updated'=>$updated]);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
