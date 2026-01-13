<?php
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Support/Session.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
App\Support\Session::start();
if (!isset($_SESSION['user_id'])) { header('Location: /adminfrequencia/login.php'); exit; }
require_once __DIR__ . '/../../src/Database/Connection.php';
use App\Database\Connection;
$pdo = Connection::get();
$escolaSess = isset($_SESSION['escola_id']) ? (int)$_SESSION['escola_id'] : null;
if (!$escolaSess) { http_response_code(401); echo 'Não autenticado'; exit; }
$minCompat = isset($_GET['min']) ? (int)$_GET['min'] : 0;
$limit = 50;
$rows = [];
try {
  $hasCompat = $pdo->query("SHOW COLUMNS FROM frequencias LIKE 'compatibilidade'")->fetch();
  if ($hasCompat) {
    $stmt = $pdo->prepare("SELECT f.id, f.data, f.hora, f.status, f.turno, f.compatibilidade, a.nome AS aluno, t.nome AS turma
                           FROM frequencias f
                           JOIN alunos a ON a.id=f.aluno_id
                           LEFT JOIN turmas t ON t.id=f.turma_id
                           WHERE f.escola_id=? AND f.data BETWEEN (CURDATE() - INTERVAL 7 DAY) AND CURDATE()
                             AND (f.compatibilidade IS NULL OR f.compatibilidade <= ?)
                           ORDER BY f.data DESC, f.hora DESC
                           LIMIT $limit");
    $stmt->execute([$escolaSess, $minCompat]);
    $rows = $stmt->fetchAll();
  } else {
    $stmt = $pdo->prepare("SELECT f.id, DATE(f.leitura_at) AS data, TIME(f.leitura_at) AS hora, f.status, a.nome AS aluno
                           FROM frequencias f
                           JOIN alunos a ON a.id=f.aluno_id
                           WHERE f.escola_id=? AND f.leitura_at >= (NOW() - INTERVAL 7 DAY)
                           ORDER BY f.leitura_at DESC
                           LIMIT $limit");
    $stmt->execute([$escolaSess]);
    $rows = $stmt->fetchAll();
  }
} catch (\Throwable $e) {}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Relatório de Frequências</title>
  <link rel="stylesheet" href="/adminfrequencia/admin.css">
  <link rel="stylesheet" href="/adminfrequencia/light.css">
  <style>
    .layout{max-width:1100px;margin:0 auto;padding:16px}
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid var(--border);padding:8px;text-align:left}
    th{background:var(--surface)}
    .actions a{margin-right:8px}
  </style>
</head>
<body>
  <div class="layout">
    <h2>Relatório de Frequências (últimos 7 dias)</h2>
    <form method="get" style="margin:8px 0">
      <label>Compatibilidade máxima:</label>
      <input name="min" class="inp" type="number" min="0" max="100" value="<?php echo (int)$minCompat; ?>" style="max-width:90px">
      <button class="btn">Filtrar</button>
    </form>
    <table>
      <thead>
        <tr>
          <th>Data</th><th>Hora</th><th>Aluno</th><th>Turma</th><th>Status</th><th>Compat.</th><th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['data'] ?? '', ENT_QUOTES); ?></td>
            <td><?php echo htmlspecialchars($r['hora'] ?? '', ENT_QUOTES); ?></td>
            <td><?php echo htmlspecialchars($r['aluno'] ?? '', ENT_QUOTES); ?></td>
            <td><?php echo htmlspecialchars($r['turma'] ?? '', ENT_QUOTES); ?></td>
            <td><?php echo htmlspecialchars($r['status'] ?? '', ENT_QUOTES); ?></td>
            <td><?php echo isset($r['compatibilidade']) ? ((int)$r['compatibilidade'].'%') : 'N/D'; ?></td>
            <td class="actions">
              <a class="btn-secondary" href="/adminfrequencia/view_frame.php?fid=<?php echo (int)$r['id']; ?>">Ver foto</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
