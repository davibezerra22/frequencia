<?php
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Support/Session.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
App\Support\Session::start();
if (!isset($_SESSION['user_id'])) {
  header('Location: /adminfrequencia/');
  exit;
}
