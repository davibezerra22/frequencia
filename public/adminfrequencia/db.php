<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
use App\Database\Connection;
$pdo = null;
$error = null;
try { $pdo = Connection::get(); } catch (\Throwable $e) { $error = 'Falha na conexão ao banco'; }
$tables = [];
if (!$error) {
    $rs = $pdo->query('SHOW TABLES');
    foreach ($rs as $row) { $tables[] = array_values($row)[0]; }
}
$sel = $_GET['t'] ?? '';
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 50;
$columns = [];
$rows = [];
$total = 0;
if ($sel && in_array($sel, $tables, true)) {
    $c = $pdo->query('DESCRIBE `'.$sel.'`');
    foreach ($c as $col) { $columns[] = $col['Field']; }
    $q = $pdo->query('SELECT COUNT(*) AS c FROM `'.$sel.'`');
    $total = (int)$q->fetch()['c'];
    $offset = ($page - 1) * $per;
    $q2 = $pdo->query('SELECT * FROM `'.$sel.'` LIMIT '.$per.' OFFSET '.$offset);
    foreach ($q2 as $r) { $rows[] = $r; }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin • Banco de Dados</title>
  <link rel="stylesheet" href="/adminfrequencia/admin.css">
</head>
<body>
  <div class="top"><div>Admin • Visualizador de Banco</div><div class="badge <?php echo $error?'err':'ok'; ?>"><?php echo $error? 'Sem conexão' : 'Conectado'; ?></div></div>
  <div class="layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div class="content">
      <div class="split">
        <div class="sidebar">
          <h3>Tabelas</h3>
          <div class="list">
            <?php if ($error){ ?>
              <div class="item">Erro de conexão</div>
            <?php } else { foreach ($tables as $t) {
                $cnt = 0;
                try { $cnt = (int)$pdo->query('SELECT COUNT(*) AS c FROM `'.$t.'`')->fetch()['c']; } catch(\Throwable $e){}
            ?>
              <div class="item">
                <a href="?t=<?php echo urlencode($t); ?>"><?php echo htmlspecialchars($t); ?></a>
                <span class="count"><?php echo $cnt; ?></span>
              </div>
            <?php } } ?>
          </div>
        </div>
        <div class="content">
      <?php if ($sel && in_array($sel, $tables, true)) { ?>
        <div style="margin-bottom:10px;color:var(--muted)">Tabela: <strong><?php echo htmlspecialchars($sel); ?></strong> • Registros: <?php echo $total; ?></div>
        <div style="overflow:auto;max-height:70vh">
          <table>
            <thead>
              <tr>
                <?php foreach ($columns as $c){ ?><th><?php echo htmlspecialchars($c); ?></th><?php } ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r){ ?>
                <tr>
                  <?php foreach ($columns as $c){ ?>
                    <td><?php echo htmlspecialchars((string)($r[$c] ?? '')); ?></td>
                  <?php } ?>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
        <div class="pager">
          <?php $pages = max(1, (int)ceil($total/$per)); $prev = max(1,$page-1); $next = min($pages,$page+1); ?>
          <a class="btn" aria-disabled="<?php echo $page<=1?'true':'false'; ?>" href="?t=<?php echo urlencode($sel); ?>&p=<?php echo $prev; ?>">Anterior</a>
          <span class="btn" aria-disabled="true">Página <?php echo $page; ?> de <?php echo $pages; ?></span>
          <a class="btn" aria-disabled="<?php echo $page>=$pages?'true':'false'; ?>" href="?t=<?php echo urlencode($sel); ?>&p=<?php echo $next; ?>">Próxima</a>
        </div>
      <?php } else { ?>
        <div style="color:var(--muted)">Selecione uma tabela ao lado para visualizar registros.</div>
      <?php } ?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
