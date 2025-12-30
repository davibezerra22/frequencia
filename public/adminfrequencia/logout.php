<?php
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
session_start();
session_unset();
session_destroy();
header('Location: /adminfrequencia/');
