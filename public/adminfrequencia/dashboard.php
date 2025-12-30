<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
use App\Database\Connection;
$ok = true;
try { Connection::get(); } catch (\Throwable $e) { $ok = false; }
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin • Dashboard</title>
  <link rel="stylesheet" href="/adminfrequencia/admin.css">
</head>
<body>
  <div class="top"><div>Admin • Sistema de Frequência</div><div class="badge <?php echo $ok?'ok':'err'; ?>"><?php echo $ok?'Banco conectado':'Falha na conexão'; ?></div></div>
  <div class="layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div class="content">
      <h2 class="title">Infraestrutura validada</h2>
      <p>Login exibido, conexão ao banco verificada.</p>
      <div class="muted">Use a barra lateral para navegar pelos CRUDs.</div>
    </div>
  </div>
</body>
</html>
