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
    try {
      $exists = $pdo->prepare('SELECT COUNT(*) AS c FROM matriculas_turma mt JOIN turmas t ON t.id=mt.turma_id WHERE mt.aluno_id=? AND t.ano_letivo_id=?');
      $exists->execute([$aluno_id, $ano_atual]);
      $c = (int)($exists->fetch()['c'] ?? 0);
      if ($c > 0) {
        $msg = 'Aluno já está enturmado no Ano atual';
      } else {
        $s = $pdo->prepare('INSERT INTO matriculas_turma (aluno_id, turma_id) VALUES (?, ?)');
        $s->execute([$aluno_id, $turma_id]);
        $msg = 'Aluno enturmado';
      }
    } catch (\Throwable $e) { $msg = 'Erro ao enturmar'; }
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
    WHERE a.id NOT IN (
      SELECT mt.aluno_id
      FROM matriculas_turma mt
      JOIN turmas t2 ON t2.id=mt.turma_id
      WHERE t2.ano_letivo_id=?
    )
      AND (a.nome LIKE ? OR a.matricula LIKE ?)
    ORDER BY a.nome
    LIMIT 50');
  $stmt2->execute([$ano_atual, $like, $like]);
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
  <?php $theme = $_GET['theme'] ?? ($_POST['theme'] ?? ''); if ($theme!=='dark'){ ?>
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
            <div class="brand">Enturmação • <?php echo htmlspecialchars($nome); ?></div>
            <div class="user">Usuário: <?php echo htmlspecialchars($user); ?></div>
          </div>
        </div>
        <span class="badge ok" style="visibility:hidden">Conectado</span>
      </div>
      <div class="row"><a class="btn-secondary" href="?<?php echo $view_escola? 'escola='.$view_escola.'&':''; ?>theme=dark">Tema escuro</a></div>
    </div>
    </div>
  <?php } else { ?>
    <div class="top"><div>Admin • Enturmação</div><div class="muted"><?php echo htmlspecialchars($msg); ?></div></div>
    <div class="layout">
      <?php require __DIR__ . '/_sidebar.php'; ?>
      <div class="content"><div class="row" style="margin:10px 0"><a class="btn" href="?<?php echo $view_escola? 'escola='.$view_escola:''; ?>">Tema claro</a></div></div>
    </div>
    </body>
    </html>
    <?php return; ?>
  <?php } ?>
  <div class="layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div class="content">
      <h2 class="title">Selecionar Turma</h2>
      <?php // no preview button needed since light is padrão ?>
      <?php if ($session_escola===null){ ?>
        <form method="get" class="row" style="gap:8px">
          <select name="escola">
            <option value="">Todas</option>
            <?php foreach ($schools as $sc){ ?>
              <option value="<?php echo $sc['id']; ?>" <?php echo ((string)$view_escola===(string)$sc['id'])?'selected':''; ?>><?php echo htmlspecialchars($sc['nome']); ?></option>
            <?php } ?>
          </select>
          <?php if (($theme ?? '')==='dark'){ ?><input type="hidden" name="theme" value="dark"><?php } ?>
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
        <?php if (($theme ?? '')==='dark'){ ?><input type="hidden" name="theme" value="dark"><?php } ?>
        <button type="submit">Abrir</button>
      </form>
      <?php if ($tid>0) { ?>
      <div class="split" style="margin-top:12px">
        <div class="content">
          <?php $count_m = count($matriculados); ?>
          <h3 class="title">Matriculados <span class="badge ok"><?php echo $count_m; ?></span></h3>
          <table class="zebra">
            <thead><tr><th>Aluno</th><th>Matrícula</th><th>Ações</th></tr></thead>
            <tbody>
              <?php foreach ($matriculados as $a){ ?>
                <tr>
                  <td>
                    <div class="row" style="align-items:center;gap:8px">
                      <?php if ($a['foto_aluno']){ ?><img src="<?php echo htmlspecialchars((string)$a['foto_aluno']); ?>" class="avatar" alt=""><?php } ?>
                      <span><?php echo htmlspecialchars($a['nome']); ?></span>
                    </div>
                  </td>
                  <td><?php echo htmlspecialchars($a['matricula']); ?></td>
                  <td style="white-space:nowrap;display:flex;gap:8px">
                    <button class="btn-secondary" type="button" onclick="openRemoveMatricula('<?php echo $a['id']; ?>','<?php echo $tid; ?>')">Remover</button>
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
          <?php $count_d = count($disponiveis); ?>
          <h3 class="title">Buscar e Adicionar <span class="badge ok"><?php echo $count_d; ?></span></h3>
          <form method="get" class="row">
            <input type="hidden" name="turma" value="<?php echo $tid; ?>">
            <input name="q" placeholder="Nome ou matrícula" value="<?php echo htmlspecialchars($q); ?>">
            <?php if (($theme ?? '')==='dark'){ ?><input type="hidden" name="theme" value="dark"><?php } ?>
            <button type="submit">Buscar</button>
          </form>
          <table class="zebra">
            <thead><tr><th>Aluno</th><th>Matrícula</th><th>Ações</th></tr></thead>
            <tbody>
              <?php foreach ($disponiveis as $a){ ?>
                <tr>
                  <td>
                    <div class="row" style="align-items:center;gap:8px">
                      <?php if ($a['foto_aluno']){ ?><img src="<?php echo htmlspecialchars((string)$a['foto_aluno']); ?>" class="avatar" alt=""><?php } ?>
                      <span><?php echo htmlspecialchars($a['nome']); ?></span>
                    </div>
                  </td>
                  <td><?php echo htmlspecialchars($a['matricula']); ?></td>
                  <td style="white-space:nowrap;display:flex;gap:8px">
                    <form method="post">
                      <input type="hidden" name="aluno_id" value="<?php echo $a['id']; ?>">
                      <input type="hidden" name="turma_id" value="<?php echo $tid; ?>">
                      <?php if (($theme ?? '')==='dark'){ ?><input type="hidden" name="theme" value="dark"><?php } ?>
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
  <script src="/adminfrequencia/modal.js"></script>
  <script>
    <?php if ($msg){ $type = stripos($msg,'Erro')!==false ? 'err' : 'ok'; ?>
      showToast('<?php echo htmlspecialchars($msg); ?>','<?php echo $type; ?>')
    <?php } ?>
  </script>
  <div class="modal-backdrop" id="modalRemoveMatricula">
    <div class="modal">
      <div class="hd"><div>Remover da Turma</div><button class="btn-secondary" type="button" onclick="closeRemoveMatricula()">Fechar</button></div>
      <div class="bd">
        <form method="post" class="row">
          <input type="hidden" name="aluno_id">
          <input type="hidden" name="turma_id">
          <button class="btn" name="act" value="remove">Confirmar Remoção</button>
        </form>
      </div>
      <div class="ft"></div>
    </div>
  </div>
</body>
</html>
