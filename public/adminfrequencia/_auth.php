<?php
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: /adminfrequencia/');
  exit;
}
