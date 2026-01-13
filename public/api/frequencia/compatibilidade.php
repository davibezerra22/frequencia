<?php
require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Database/Connection.php';
require_once __DIR__ . '/../../../src/Support/Env.php';
require_once __DIR__ . '/../../../src/Support/Session.php';
require_once __DIR__ . '/../../../src/Bootstrap.php';
use App\Database\Connection;
header('Content-Type: application/json; charset=utf-8');
App\Support\Session::start();
$pdo = Connection::get();
$escolaSess = isset($_SESSION['escola_id']) ? (int)$_SESSION['escola_id'] : null;
if (!$escolaSess) { http_response_code(401); echo json_encode(['status'=>'erro','mensagem'=>'Não autenticado']); exit; }
$input = json_decode(file_get_contents('php://input'), true);
$alunoId = isset($input['aluno_id']) ? (int)$input['aluno_id'] : 0;
$compat = isset($input['compatibilidade']) ? (int)$input['compatibilidade'] : null;
$device = isset($input['device_id']) ? (string)$input['device_id'] : '';
if ($alunoId<=0 || $compat===null) { http_response_code(400); echo json_encode(['status'=>'erro','mensagem'=>'Dados inválidos']); exit; }
try {
  $col = $pdo->query("SHOW COLUMNS FROM frequencias LIKE 'compatibilidade'")->fetch();
  if (!$col) { echo json_encode(['status'=>'ok','mensagem'=>'Campo compatibilidade indisponível']); exit; }
  $hasData = $pdo->query("SHOW COLUMNS FROM frequencias LIKE 'data'")->fetch();
  $hasHora = $pdo->query("SHOW COLUMNS FROM frequencias LIKE 'hora'")->fetch();
  $hasLeitura = $pdo->query("SHOW COLUMNS FROM frequencias LIKE 'leitura_at'")->fetch();
  $id = null;
  if ($hasData && $hasHora) {
    $q = $pdo->prepare("SELECT id FROM frequencias WHERE escola_id=? AND aluno_id=? AND data=CURDATE() ORDER BY hora DESC LIMIT 1");
    $q->execute([$escolaSess, $alunoId]);
    $row = $q->fetch();
    $id = $row ? (int)$row['id'] : null;
  } elseif ($hasLeitura) {
    $q = $pdo->prepare("SELECT id FROM frequencias WHERE escola_id=? AND aluno_id=? AND DATE(leitura_at)=CURDATE() ORDER BY leitura_at DESC LIMIT 1");
    $q->execute([$escolaSess, $alunoId]);
    $row = $q->fetch();
    $id = $row ? (int)$row['id'] : null;
  }
  if (!$id) { echo json_encode(['status'=>'ok','mensagem'=>'Registro não encontrado']); exit; }
  $u = $pdo->prepare("UPDATE frequencias SET compatibilidade=? WHERE id=?");
  $u->execute([$compat, $id]);
  echo json_encode(['status'=>'ok','mensagem'=>'Compatibilidade atualizada','compatibilidade'=>$compat]);
} catch (\Throwable $e) {
  http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]);
}
