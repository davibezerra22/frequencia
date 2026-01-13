<?php
require_once __DIR__ . '/../../../src/Support/Env.php';
require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Database/Connection.php';
require_once __DIR__ . '/../../../src/Bootstrap.php';
use App\Database\Connection;
header('Content-Type: application/json');
$pdo = Connection::get();
$id = (int)($_GET['id'] ?? 0);
if ($id<=0) { echo json_encode(['status'=>'error','message'=>'invalid_id']); exit; }
try {
  $row = $pdo->prepare('SELECT face_encoding FROM alunos WHERE id=?'); $row->execute([$id]); $r = $row->fetch();
  if (!$r || !$r['face_encoding']) { echo json_encode(['status'=>'error','message'=>'no_encoding']); exit; }
  $enc = json_decode((string)$r['face_encoding'], true);
  if (!is_array($enc)) { echo json_encode(['status'=>'error','message'=>'bad_encoding']); exit; }
  echo json_encode(['status'=>'ok','encoding'=>$enc]);
} catch (\Throwable $e) {
  echo json_encode(['status'=>'error','message'=>'exception']);
}
