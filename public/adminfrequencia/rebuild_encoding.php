<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
use App\Database\Connection;
$pdo = Connection::get();
header('Content-Type: application/json');
$id = (int)($_GET['id'] ?? 0);
if ($id<=0) { echo json_encode(['status'=>'error','message'=>'invalid_id']); exit; }
try {
  $row = $pdo->prepare('SELECT foto_aluno FROM alunos WHERE id=?'); $row->execute([$id]); $r=$row->fetch();
  if (!$r || !$r['foto_aluno']) { echo json_encode(['status'=>'error','message'=>'no_photo']); exit; }
  $pathWeb = (string)$r['foto_aluno'];
  $pub = dirname(__DIR__);
  $local = $pathWeb;
  if ($local && $local[0]==='/') { $local = $pub . $local; }
  $data = null;
  if (is_file($local)) { $data = @file_get_contents($local); }
  $payload = [];
  if ($data!==false && $data!==null) { $payload['image_base64'] = base64_encode($data); } else { $payload['image_url'] = $pathWeb; }
  $payload['student_id'] = $id;
  $api = 'http://127.0.0.1:8787/gerar-encoding';
  $resp = null; $code = null;
  $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
  if (function_exists('curl_init')) {
    $ch = curl_init($api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
  } else {
    $context = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $body,
        'timeout' => 6
      ]
    ]);
    $resp = @file_get_contents($api, false, $context);
    if (isset($http_response_header[0]) && preg_match('#HTTP/\\S+\\s+(\\d+)#',$http_response_header[0],$m)) { $code = (int)$m[1]; }
  }
  if (!$resp || $code!==200) { echo json_encode(['status'=>'error','http'=>$code]); exit; }
  $obj = @json_decode($resp, true);
  if (!is_array($obj) || ($obj['status'] ?? '')!=='ok' || !isset($obj['encoding']) || !is_array($obj['encoding'])) {
    echo json_encode(['status'=>'error','message'=>'bad_response']); exit;
  }
  $encJson = json_encode($obj['encoding']);
  $pdo->prepare('UPDATE alunos SET face_encoding=? WHERE id=?')->execute([$encJson, $id]);
  echo json_encode(['status'=>'ok','len'=>strlen($encJson)]);
} catch (\Throwable $e) {
  echo json_encode(['status'=>'error','message'=>'exception']);
}
