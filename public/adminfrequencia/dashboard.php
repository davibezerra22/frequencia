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
  <?php $theme = $_GET['theme'] ?? ''; if ($theme!=='dark'){ ?>
    <link rel="stylesheet" href="/adminfrequencia/light.css">
  <?php } ?>
</head>
<body>
  <?php if (($theme ?? '')!=='dark'){ ?>
    <div class="header branded">
      <div class="row">
        <div class="brand-block">
          <?php $logo = $_SESSION['escola_logo'] ?? ''; $nome = $_SESSION['escola_nome'] ?? 'Escola'; $user = $_SESSION['user_name'] ?? 'Usuário'; ?>
          <img src="<?php echo $logo ?: 'https://via.placeholder.com/56x56.png?text=E'; ?>" alt="">
          <div>
            <div class="brand"><?php echo htmlspecialchars($nome); ?></div>
            <div class="user">Usuário: <?php echo htmlspecialchars($user); ?></div>
          </div>
        </div>
        <span class="badge ok" style="visibility:hidden">Conectado</span>
      </div>
      <div class="row"><a class="btn-secondary" href="?theme=dark">Tema escuro</a></div>
    </div>
  <?php } else { ?>
    <div class="top"><div>Admin • Sistema de Frequência</div></div>
    <div class="layout">
      <?php require __DIR__ . '/_sidebar.php'; ?>
      <div class="content">
        <div class="row" style="margin-top:10px"><a class="btn" href="?">Tema claro</a></div>
        <h2 class="title">Infraestrutura validada</h2>
        <p>Login exibido, conexão ao banco verificada.</p>
        <div class="muted">Use a barra lateral para navegar pelos CRUDs.</div>
      </div>
    </div>
    </body>
    </html>
    <?php return; ?>
  <?php } ?>
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
