<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
use App\Database\Connection;
$pdo = Connection::get();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'create_serie') {
        $nome = trim($_POST['nome'] ?? '');
        $eid = $session_escola ?? $view_escola;
        if (!$eid) { $msg = 'Selecione uma escola (superadmin)'; }
        else {
          $s = $pdo->prepare('INSERT INTO series (nome, escola_id) VALUES (?, ?)');
          try { $s->execute([$nome, $eid]); $msg = 'Série criada'; } catch (\Throwable $e) { $msg = 'Erro ao criar série'; }
        }
    } elseif ($act === 'update_serie') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $s = $pdo->prepare('UPDATE series SET nome=? WHERE id=?');
        try { $s->execute([$nome, $id]); $msg = 'Série atualizada'; } catch (\Throwable $e) { $msg = 'Erro ao atualizar série'; }
    } elseif ($act === 'delete_serie') {
        $id = (int)($_POST['id'] ?? 0);
        try { $pdo->prepare('DELETE FROM series WHERE id=?')->execute([$id]); $msg = 'Série excluída'; } catch (\Throwable $e) { $msg = 'Erro ao excluir série'; }
    } elseif ($act === 'create_turma') {
        $serie_id = (int)($_POST['serie_id'] ?? 0);
        $ano_letivo_id = (int)($_POST['ano_letivo_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if ($serie_id<=0 || $ano_letivo_id<=0 || $nome==='') {
            $msg = 'Selecione série, ano letivo e informe o nome da turma';
        } else {
            $eid = $_SESSION['escola_id'] ?? null;
            $s = $pdo->prepare('INSERT INTO turmas (serie_id, ano_letivo_id, nome, escola_id) VALUES (?, ?, ?, ?)');
            try { $s->execute([$serie_id, $ano_letivo_id, $nome, $eid]); $msg = 'Turma criada'; } catch (\Throwable $e) { $msg = 'Erro ao criar turma'; }
        }
    } elseif ($act === 'update_turma') {
        $id = (int)($_POST['id'] ?? 0);
        $serie_id = (int)($_POST['serie_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $s = $pdo->prepare('UPDATE turmas SET serie_id=?, nome=? WHERE id=?');
        try { $s->execute([$serie_id, $nome, $id]); $msg = 'Turma atualizada'; } catch (\Throwable $e) { $msg = 'Erro ao atualizar turma'; }
    } elseif ($act === 'delete_turma') {
        $id = (int)($_POST['id'] ?? 0);
        $s = $pdo->prepare('DELETE FROM turmas WHERE id=?');
        try { $s->execute([$id]); $msg = 'Turma excluída'; } catch (\Throwable $e) { $msg = 'Erro ao excluir turma'; }
    }
}
$session_escola = $_SESSION['escola_id'] ?? null;
$view_escola = $session_escola ?: (int)($_GET['escola'] ?? 0) ?: null;
$schools = [];
if ($session_escola === null) { $schools = $pdo->query('SELECT id, nome FROM escolas ORDER BY nome')->fetchAll(); }
$series_stmt = $view_escola
  ? $pdo->prepare('SELECT id, nome FROM series WHERE escola_id <=> ? ORDER BY nome')
  : $pdo->prepare('SELECT id, nome FROM series ORDER BY nome');
if ($view_escola !== null) { $series_stmt->execute([$view_escola]); } else { $series_stmt->execute(); }
$series = $series_stmt->fetchAll();
$anos_stmt = $view_escola
  ? $pdo->prepare('SELECT id, ano FROM anos_letivos WHERE escola_id=? ORDER BY ano DESC')
  : $pdo->prepare('SELECT id, ano FROM anos_letivos ORDER BY ano DESC');
if ($view_escola) { $anos_stmt->execute([$view_escola]); } else { $anos_stmt->execute(); }
$anos = $anos_stmt->fetchAll();
$ano_atual = $view_escola
  ? ($pdo->prepare("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id' AND escola_id=?")->execute([$view_escola]) ? ($pdo->query("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id' AND escola_id=".$view_escola)->fetch()['valor'] ?? null) : null)
  : ($pdo->query("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id'")->fetch()['valor'] ?? null);
$turmas = [];
if ($ano_atual) {
    $s = $pdo->prepare('SELECT t.id, t.nome, t.serie_id, s.nome AS serie_nome FROM turmas t JOIN series s ON s.id=t.serie_id WHERE t.ano_letivo_id=? ORDER BY s.nome, t.nome');
    $s->execute([$ano_atual]);
    $turmas = $s->fetchAll();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin • Turmas</title>
  <link rel="stylesheet" href="/adminfrequencia/admin.css">
</head>
<body>
  <div class="top"><div>Admin • Turmas</div><div class="muted"><?php echo htmlspecialchars($msg); ?></div></div>
  <div class="layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div class="content">
      <div class="row" style="align-items:center;justify-content:space-between">
        <div>
          <h2 class="title" style="margin:0">Turmas do Ano Atual</h2>
          <div class="muted">Ano atual: <?php echo $ano_atual ? htmlspecialchars($pdo->query('SELECT ano FROM anos_letivos WHERE id='.$ano_atual)->fetch()['ano'] ?? '') : 'defina em Períodos'; ?></div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
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
          <button class="btn" type="button" onclick="openTurmas()">Séries/Turmas</button>
        </div>
      </div>
      <table>
        <thead><tr><th>Série</th><th>Nome da Turma</th></tr></thead>
        <tbody>
          <?php foreach ($turmas as $t){ ?>
            <tr>
              <td><?php echo htmlspecialchars($t['serie_nome']); ?></td>
              <td><?php echo htmlspecialchars($t['nome']); ?></td>
            </tr>
          <?php } ?>
          <?php if (!$turmas){ ?>
            <tr><td colspan="2" class="muted">Nenhuma turma cadastrada no Ano atual.</td></tr>
          <?php } ?>
        </tbody>
      </table>
      <div class="modal-backdrop" id="modalTurmas">
        <div class="modal">
          <div class="hd"><div>Gestão de Séries e Turmas</div><button class="btn-secondary" type="button" onclick="closeTurmas()">Fechar</button></div>
          <div class="bd">
            <div class="tabs">
              <button class="tab active" data-tab="tab-series">Séries</button>
              <button class="tab" data-tab="tab-turmas">Turmas</button>
            </div>
            <div id="tab-series">
              <form method="post" class="row">
                <input name="nome" placeholder="Ex.: 1º Ano, 2º Ano">
                <button name="act" value="create_serie">Criar Série</button>
              </form>
              <table>
                <thead><tr><th>Nome da Série</th><th>Ações</th></tr></thead>
                <tbody>
                  <?php foreach ($series as $s){ ?>
                    <tr>
                      <form method="post">
                        <td><input name="nome" value="<?php echo htmlspecialchars($s['nome']); ?>"></td>
                        <td style="white-space:nowrap;display:flex;gap:8px">
                          <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                          <button name="act" value="update_serie">Salvar</button>
                          <button name="act" value="delete_serie" onclick="return confirm('Excluir série?')">Excluir</button>
                        </td>
                      </form>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
            <div id="tab-turmas" style="display:none">
              <form method="post" class="row">
                <select name="serie_id">
                  <?php foreach ($series as $s){ ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nome']); ?></option>
                  <?php } ?>
                </select>
                <input name="nome" placeholder="Nome da Turma">
                <?php if ($anos) { ?>
                  <select name="ano_letivo_id">
                    <?php foreach ($anos as $a){ ?>
                      <option value="<?php echo $a['id']; ?>" <?php echo ((string)$a['id']===(string)$ano_atual)?'selected':''; ?>><?php echo htmlspecialchars($a['ano']); ?></option>
                    <?php } ?>
                  </select>
                  <button name="act" value="create_turma">Criar Turma</button>
                <?php } else { ?>
                  <select disabled><option>Crie um Ano Letivo em Períodos</option></select>
                  <button disabled aria-disabled="true">Criar Turma</button>
                <?php } ?>
              </form>
              <table>
                <thead><tr><th>Série</th><th>Nome da Turma</th><th>Ações</th></tr></thead>
                <tbody>
                  <?php foreach ($turmas as $t){ ?>
                    <tr>
                      <form method="post">
                        <td>
                          <select name="serie_id">
                            <?php foreach ($series as $s){ ?>
                              <option value="<?php echo $s['id']; ?>" <?php echo ((string)$s['id']===(string)$t['serie_id'])?'selected':''; ?>><?php echo htmlspecialchars($s['nome']); ?></option>
                            <?php } ?>
                          </select>
                        </td>
                        <td><input name="nome" value="<?php echo htmlspecialchars($t['nome']); ?>"></td>
                        <td style="white-space:nowrap;display:flex;gap:8px">
                          <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                          <button name="act" value="update_turma">Salvar</button>
                          <button name="act" value="delete_turma" onclick="return confirm('Excluir turma?')">Excluir</button>
                        </td>
                      </form>
                    </tr>
                  <?php } ?>
                  <?php if (!$turmas){ ?>
                    <tr><td colspan="3" class="muted">Nenhuma turma cadastrada.</td></tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="ft"><button class="btn-secondary" type="button" onclick="closeTurmas()">Concluir</button></div>
        </div>
      </div>
    </div>
  </div>
<script src="/adminfrequencia/modal.js"></script>
</body>
</html>
