<?php
require_once __DIR__ . '/../../../src/Support/Env.php';
require_once __DIR__ . '/../../../src/Support/Session.php';
require_once __DIR__ . '/../../../src/Database/Connection.php';
require_once __DIR__ . '/../../../src/Bootstrap.php';
App\Support\Session::start();
header('Content-Type: image/jpeg');
try {
  $pdo = \App\Database\Connection::get();
  $eid = isset($_SESSION['escola_id']) ? (int)$_SESSION['escola_id'] : null;
  if (!$eid) { http_response_code(401); exit; }
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id<=0){ http_response_code(404); exit; }
$st = $pdo->prepare('SELECT foto_aluno FROM alunos WHERE id=? AND escola_id=?');
  $st->execute([$id, $eid]);
  $row = $st->fetch();
  if (!$row) { http_response_code(404); exit; }
  $path = trim((string)$row['foto_aluno'] ?? '');
  if ($path==='') { http_response_code(404); exit; }
  $url = null;
  if (strpos($path, 'http://')===0 || strpos($path, 'https://')===0) {
    $url = $path;
  } elseif ($path[0] === '/') {
    $base = 'http://127.0.0.1:8000';
    $url = rtrim($base,'/').$path;
  }
  if ($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    $data = curl_exec($ch);
    $ct = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($data===false){ http_response_code(404); exit; }
    if ($ct) { header('Content-Type: '.$ct); }
    echo $data;
    exit;
  }
  $pub = dirname(__DIR__,2);
  $local = $path;
  if (!is_file($local)) {
    $local = $pub . DIRECTORY_SEPARATOR . ltrim($path, '\\/');
  }
  if (is_file($local)) {
    $finfo = function_exists('mime_content_type') ? mime_content_type($local) : null;
    if ($finfo) { header('Content-Type: '.$finfo); }
    $fp = fopen($local, 'rb');
    if ($fp){ fpassthru($fp); fclose($fp); exit; }
  }
  http_response_code(404);
} catch (\Throwable $e) {
  http_response_code(500);
}
