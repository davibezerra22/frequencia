<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
use App\Database\Connection;
$pdo = Connection::get();
$msg = '';
$session_escola = $_SESSION['escola_id'] ?? null;
$view_escola = $session_escola ?: (int)($_GET['escola'] ?? 0) ?: null;
$schools = [];
if ($session_escola === null) { $schools = $pdo->query('SELECT id, nome FROM escolas ORDER BY nome')->fetchAll(); }
$ano_atual = $view_escola
  ? ($pdo->prepare("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id' AND escola_id=?")->execute([$view_escola]) ? ($pdo->query("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id' AND escola_id=".$view_escola)->fetch()['valor'] ?? null) : null)
  : ($pdo->query("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id'")->fetch()['valor'] ?? null);
$turmas = [];
if ($ano_atual) {
    $s = $pdo->prepare('SELECT t.id, t.nome, s.nome AS serie FROM turmas t JOIN series s ON s.id=t.serie_id WHERE t.ano_letivo_id=? ORDER BY s.nome, t.nome');
    $s->execute([$ano_atual]);
    $turmas = $s->fetchAll();
}
$tid = (int)($_GET['turma'] ?? 0);
$q = trim($_GET['q'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['act'] ?? '';
  if ($act === 'add') {
    $aluno_id = (int)($_POST['aluno_id'] ?? 0);
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $s = $pdo->prepare('INSERT IGNORE INTO matriculas_turma (aluno_id, turma_id) VALUES (?, ?)');
    try { $s->execute([$aluno_id, $turma_id]); $msg = 'Aluno enturmado'; } catch (\Throwable $e) { $msg = 'Erro ao enturmar'; }
    $tid = $turma_id;
  } elseif ($act === 'remove') {
    $aluno_id = (int)($_POST['aluno_id'] ?? 0);
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $s = $pdo->prepare('DELETE FROM matriculas_turma WHERE aluno_id=? AND turma_id=?');
    try { $s->execute([$aluno_id, $turma_id]); $msg = 'Aluno removido da turma'; } catch (\Throwable $e) { $msg = 'Erro ao remover'; }
    $tid = $turma_id;
  }
}
$matriculados = [];
$disponiveis = [];
if ($tid > 0) {
  $stmt = $pdo->prepare('SELECT a.id,a.nome,a.matricula,a.foto_aluno
    FROM matriculas_turma mt JOIN alunos a ON a.id=mt.aluno_id
    WHERE mt.turma_id=? ORDER BY a.nome');
  $stmt->execute([$tid]);
  $matriculados = $stmt->fetchAll();
  $like = '%'.$q.'%';
  $stmt2 = $pdo->prepare('SELECT a.id,a.nome,a.matricula,a.foto_aluno
    FROM alunos a
    WHERE a.id NOT IN (SELECT aluno_id FROM matriculas_turma WHERE turma_id=?)
      AND (a.nome LIKE ? OR a.matricula LIKE ?)
    ORDER BY a.nome
    LIMIT 50');
  $stmt2->execute([$tid, $like, $like]);
  $disponiveis = $stmt2->fetchAll();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin • Enturmação</title>
  <link rel="stylesheet" href="/adminfrequencia/admin.css">
  <?php $theme = $_GET['theme'] ?? ''; if ($theme==='light'){ ?>
    <link rel="stylesheet" href="/adminfrequencia/light.css">
  <?php } ?>
</head>
<body>
  <?php if (($theme ?? '')==='light'){ ?>
    <div class="header branded">
      <div class="row">
        <div class="brand-block">
          <?php $logo = $_SESSION['escola_logo'] ?? ''; $nome = $_SESSION['escola_nome'] ?? 'Escola'; $user = $_SESSION['user_name'] ?? 'Usuário'; ?>
          <img src="<?php echo $logo ?: 'https://via.placeholder.com/56x56.png?text=E'; ?>" alt="">
          <div>
            <div class="brand">Enturmação • <?php echo htmlspecialchars($nome); ?></div>
            <div class="user">Usuário: <?php echo htmlspecialchars($user); ?></div>
          </div>
        </div>
        <span class="badge ok"><?php echo htmlspecialchars($msg); ?></span>
      </div>
      <div class="row"><a class="btn-secondary" href="?<?php echo $view_escola? 'escola='.$view_escola:''; ?>">Tema escuro</a></div>
    </div>
  <?php } else { ?>
    <div class="top"><div>Admin • Enturmação</div><div class="muted"><?php echo htmlspecialchars($msg); ?></div></div>
  <?php } ?>
  <div class="layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div class="content">
      <h2 class="title">Selecionar Turma</h2>
      <?php if (($theme ?? '')!=='light'){ ?>
        <div class="row" style="margin:10px 0"><a class="btn" href="?<?php echo $view_escola? 'escola='.$view_escola.'&':''; ?>theme=light">Preview tema claro</a></div>
      <?php } ?>
      <?php if ($session_escola===null){ ?>
        <form method="get" class="row" style="gap:8px">
          <select name="escola">
            <option value="">Todas</option>
            <?php foreach ($schools as $sc){ ?>
              <option value="<?php echo $sc['id']; ?>" <?php echo ((string)$view_escola===(string)$sc['id'])?'selected':''; ?>><?php echo htmlspecialchars($sc['nome']); ?></option>
            <?php } ?>
          </select>
          <button type="submit">Filtrar</button>
        </form>
      <?php } ?>
      <form method="get" class="row">
        <select name="turma" required>
          <option value="">Escolha a turma (Ano atual)</option>
          <?php foreach ($turmas as $t){ ?>
            <option value="<?php echo $t['id']; ?>" <?php echo $tid===$t['id']?'selected':''; ?>><?php echo htmlspecialchars($t['serie'].' • '.$t['nome']); ?></option>
          <?php } ?>
        </select>
        <button type="submit">Abrir</button>
      </form>
      <?php if ($tid>0) { ?>
      <div class="split" style="margin-top:12px">
        <div class="content">
          <h3 class="title">Matriculados</h3>
          <table>
            <thead><tr><th>Aluno</th><th>Matrícula</th><th>Ações</th></tr></thead>
            <tbody>
              <?php foreach ($matriculados as $a){ ?>
                <tr>
                  <td><?php echo htmlspecialchars($a['nome']); ?></td>
                  <td><?php echo htmlspecialchars($a['matricula']); ?></td>
                  <td style="white-space:nowrap;display:flex;gap:8px">
                    <form method="post">
                      <input type="hidden" name="aluno_id" value="<?php echo $a['id']; ?>">
                      <input type="hidden" name="turma_id" value="<?php echo $tid; ?>">
                      <button name="act" value="remove">Remover</button>
                    </form>
                  </td>
                </tr>
              <?php } ?>
              <?php if (!$matriculados){ ?>
                <tr><td colspan="3" class="muted">Sem alunos matriculados nesta turma.</td></tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
        <div class="content">
          <h3 class="title">Buscar e Adicionar</h3>
          <form method="get" class="row">
            <input type="hidden" name="turma" value="<?php echo $tid; ?>">
            <input name="q" placeholder="Nome ou matrícula" value="<?php echo htmlspecialchars($q); ?>">
            <button type="submit">Buscar</button>
          </form>
          <table>
            <thead><tr><th>Aluno</th><th>Matrícula</th><th>Ações</th></tr></thead>
            <tbody>
              <?php foreach ($disponiveis as $a){ ?>
                <tr>
                  <td><?php echo htmlspecialchars($a['nome']); ?></td>
                  <td><?php echo htmlspecialchars($a['matricula']); ?></td>
                  <td style="white-space:nowrap;display:flex;gap:8px">
                    <form method="post">
                      <input type="hidden" name="aluno_id" value="<?php echo $a['id']; ?>">
                      <input type="hidden" name="turma_id" value="<?php echo $tid; ?>">
                      <button name="act" value="add">Adicionar</button>
                    </form>
                  </td>
                </tr>
              <?php } ?>
              <?php if (!$disponiveis){ ?>
                <tr><td colspan="3" class="muted">Nenhum aluno disponível para adicionar. Ajuste a busca.</td></tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php } ?>
    </div>
  </div>
</body>
</html>
