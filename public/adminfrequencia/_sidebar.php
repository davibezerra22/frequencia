<?php
$user = $_SESSION['user_name'] ?? '';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
use App\Database\Connection;
$schoolName = '';
$schoolLogo = '';
try {
  $pdo = Connection::get();
  $eid = $_SESSION['escola_id'] ?? null;
  if ($eid) {
    $st = $pdo->prepare('SELECT nome, logotipo FROM escolas WHERE id=?');
    $st->execute([$eid]);
    $row = $st->fetch();
    if ($row) { $schoolName = (string)$row['nome']; $schoolLogo = (string)$row['logotipo']; }
  }
} catch (\Throwable $e) {}
?>
<div class="sidebar">
  <div class="brand">Admin • Frequência</div>
  <div class="muted" style="margin-bottom:8px"><?php echo htmlspecialchars($user); ?></div>
  <?php if ($schoolName){ ?>
    <div class="muted" style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
      <?php if ($schoolLogo){ ?><img src="<?php echo htmlspecialchars($schoolLogo); ?>" alt="" style="height:24px"><?php } ?>
      <span><?php echo htmlspecialchars($schoolName); ?></span>
    </div>
  <?php } ?>
  <div class="nav">
    <a href="/adminfrequencia/dashboard.php">Dashboard</a>
    <a href="/adminfrequencia/periodos.php">Períodos</a>
    <a href="/adminfrequencia/turmas.php">Séries e Turmas</a>
    <a href="/adminfrequencia/enturmacao.php">Enturmação</a>
    <a href="/adminfrequencia/alunos.php">Alunos</a>
    <a href="/adminfrequencia/importar_alunos.php">Importar Alunos</a>
    <a href="/adminfrequencia/db.php">Banco (read-only)</a>
    <?php if (($_SESSION['user_role'] ?? '') === 'admin'){ ?>
      <a href="/adminfrequencia/usuarios.php">Usuários</a>
    <?php } ?>
    <?php if (!isset($_SESSION['escola_id']) || $_SESSION['escola_id']===null){ ?>
      <a href="/adminfrequencia/escolas.php">Escolas</a>
    <?php } ?>
    <a href="/adminfrequencia/logout.php">Sair</a>
  </div>
</div>
