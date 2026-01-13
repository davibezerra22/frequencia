<?php
require_once __DIR__ . '/../../src/Support/Session.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
App\Support\Session::start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
require_once __DIR__ . '/../../src/Database/Connection.php';
use App\Database\Connection;
$pdo = Connection::get();
$escolaSess = isset($_SESSION['escola_id']) ? (int)$_SESSION['escola_id'] : null;
if (!$escolaSess) { http_response_code(401); exit; }
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id<=0){ http_response_code(404); exit; }
$q = $pdo->prepare("SELECT path FROM frequencias_fotos WHERE id=? AND escola_id=?");
$q->execute([$id, $escolaSess]);
$row = $q->fetch();
if (!$row){ http_response_code(404); exit; }
$path = $row['path'];
if (!is_file($path)){ http_response_code(404); exit; }
header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($path));
readfile($path);
