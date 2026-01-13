<?php
require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Database/Connection.php';
require_once __DIR__ . '/../../../src/Support/Env.php';
require_once __DIR__ . '/../../../src/Support/Session.php';
require_once __DIR__ . '/../../../src/Bootstrap.php';
use App\Database\Connection;
use App\Support\Env;
header('Content-Type: application/json; charset=utf-8');
App\Support\Session::start();
$pdo = Connection::get();
$escolaSess = isset($_SESSION['escola_id']) ? (int)$_SESSION['escola_id'] : null;
if (!$escolaSess) { http_response_code(401); echo json_encode(['status'=>'erro','mensagem'=>'Não autenticado']); exit; }
$input = json_decode(file_get_contents('php://input'), true);
$alunoId = isset($input['aluno_id']) ? (int)$input['aluno_id'] : 0;
$device = isset($input['device_id']) ? (string)$input['device_id'] : '';
$frameB64 = isset($input['frame_base64']) ? (string)$input['frame_base64'] : '';
$status = isset($input['status']) ? (string)$input['status'] : '';
try {
  $enabled = Env::get('FRAMES_SAVE_ENABLED','0');
  if ($enabled !== '1') { echo json_encode(['status'=>'ok','mensagem'=>'Salvamento de frames desabilitado']); exit; }
  if ($frameB64 === '') { http_response_code(400); echo json_encode(['status'=>'erro','mensagem'=>'Frame vazio']); exit; }
  $dir = Env::get('FRAMES_STORAGE_DIR', dirname(__DIR__,3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'frames');
  $yy = date('Y'); $mm = date('m'); $dd = date('d');
  $base = rtrim($dir, "\\/") . DIRECTORY_SEPARATOR . $escolaSess . DIRECTORY_SEPARATOR . $yy . DIRECTORY_SEPARATOR . $mm . DIRECTORY_SEPARATOR . $dd;
  if (!is_dir($base)) { @mkdir($base, 0777, true); }
  $hasData = $pdo->query("SHOW COLUMNS FROM frequencias LIKE 'data'")->fetch();
  $hasHora = $pdo->query("SHOW COLUMNS FROM frequencias LIKE 'hora'")->fetch();
  $hasLeitura = $pdo->query("SHOW COLUMNS FROM frequencias LIKE 'leitura_at'")->fetch();
  $freqId = null;
  if ($alunoId>0) {
    if ($hasData && $hasHora) {
      $q = $pdo->prepare("SELECT id FROM frequencias WHERE escola_id=? AND aluno_id=? AND data=CURDATE() ORDER BY hora DESC LIMIT 1");
      $q->execute([$escolaSess, $alunoId]); $row = $q->fetch(); $freqId = $row ? (int)$row['id'] : null;
    } elseif ($hasLeitura) {
      $q = $pdo->prepare("SELECT id FROM frequencias WHERE escola_id=? AND aluno_id=? AND DATE(leitura_at)=CURDATE() ORDER BY leitura_at DESC LIMIT 1");
      $q->execute([$escolaSess, $alunoId]); $row = $q->fetch(); $freqId = $row ? (int)$row['id'] : null;
    }
  }
  $bin = base64_decode($frameB64);
  if ($bin === false || strlen($bin) < 64) { http_response_code(400); echo json_encode(['status'=>'erro','mensagem'=>'Frame inválido']); exit; }
  $fname = ($freqId ?: $alunoId ?: 0) . '-' . time() . '-' . substr(hash('sha256',$device.'|'.$status.'|'.uniqid('',true)),0,12) . '.jpg';
  $path = $base . DIRECTORY_SEPARATOR . $fname;
  file_put_contents($path, $bin);
  $size = @filesize($path);
  $ins = $pdo->prepare("INSERT INTO frequencias_fotos (frequencia_id, escola_id, path, media_type, width, height, size_bytes) VALUES (?, ?, ?, 'frame', NULL, NULL, ?)");
  $ins->execute([$freqId, $escolaSess, $path, $size]);
  echo json_encode(['status'=>'ok','mensagem'=>'Frame salvo','foto_id'=>$pdo->lastInsertId()]);
} catch (\Throwable $e) {
  http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]);
}
