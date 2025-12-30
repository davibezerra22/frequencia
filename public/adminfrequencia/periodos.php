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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'create_ano') {
        $target_escola = $session_escola ?: (int)($_POST['escola'] ?? 0) ?: null;
        if (!$target_escola) { $msg = 'Selecione uma escola (superadmin)'; }
        else {
            $ano = (int)($_POST['ano'] ?? date('Y'));
            $status = $_POST['status'] ?? 'ativo';
            $s = $pdo->prepare('INSERT INTO anos_letivos (escola_id, ano, status) VALUES (?, ?, ?)');
            try { $s->execute([$target_escola, $ano, $status]); $msg = 'Ano letivo criado'; } catch (\Throwable $e) { $msg = 'Erro ao criar ano'; }
        }
    } elseif ($act === 'create_periodo') {
        $ano_letivo_id = (int)($_POST['ano_letivo_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $data_inicio = $_POST['data_inicio'] ?? '';
        $data_fim = $_POST['data_fim'] ?? '';
        $s = $pdo->prepare('INSERT INTO periodos_letivos (ano_letivo_id, nome, data_inicio, data_fim) VALUES (?, ?, ?, ?)');
        try { $s->execute([$ano_letivo_id, $nome, $data_inicio, $data_fim]); $msg = 'Período criado'; } catch (\Throwable $e) { $msg = 'Erro ao criar período'; }
    } elseif ($act === 'update_periodo') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $data_inicio = $_POST['data_inicio'] ?? '';
        $data_fim = $_POST['data_fim'] ?? '';
        $s = $pdo->prepare('UPDATE periodos_letivos SET nome=?, data_inicio=?, data_fim=? WHERE id=?');
        try { $s->execute([$nome, $data_inicio, $data_fim, $id]); $msg = 'Período atualizado'; } catch (\Throwable $e) { $msg = 'Erro ao atualizar período'; }
    } elseif ($act === 'delete_periodo') {
        $id = (int)($_POST['id'] ?? 0);
        $s = $pdo->prepare('DELETE FROM periodos_letivos WHERE id=?');
        try { $s->execute([$id]); $msg = 'Período excluído'; } catch (\Throwable $e) { $msg = 'Erro ao excluir período'; }
    } elseif ($act === 'set_ano_atual') {
        $ano_id = (int)($_POST['ano_letivo_id'] ?? 0);
        $target_escola = $session_escola ?: (int)($_POST['escola'] ?? 0) ?: null;
        $s = $pdo->prepare('UPDATE configuracoes SET valor=? WHERE chave=? AND (escola_id <=> ?)');
        try {
            $s->execute([$ano_id, 'ano_letivo_atual_id', $target_escola]);
            if ($s->rowCount() === 0) { $pdo->prepare('INSERT INTO configuracoes (escola_id, chave, valor) VALUES (?, ?, ?)')->execute([$target_escola, 'ano_letivo_atual_id', $ano_id]); }
            $msg = 'Ano letivo atual definido';
        } catch (\Throwable $e) { $msg = 'Erro ao definir ano atual'; }
    }
}
$escolas = $schools;
$escola_atual = $view_escola;
$anos_stmt = $view_escola
  ? $pdo->prepare('SELECT id, ano, status FROM anos_letivos WHERE escola_id=? ORDER BY ano DESC')
  : $pdo->prepare('SELECT id, ano, status FROM anos_letivos ORDER BY ano DESC');
if ($view_escola) { $anos_stmt->execute([$view_escola]); } else { $anos_stmt->execute(); }
$anos = $anos_stmt->fetchAll();
$ano_atual = $view_escola
  ? ($pdo->prepare("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id' AND escola_id=?")->execute([$view_escola]) ? ($pdo->query("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id' AND escola_id=".$view_escola)->fetch()['valor'] ?? null) : null)
  : ($pdo->query("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id'")->fetch()['valor'] ?? null);
$periodos_map = [];
foreach ($anos as $a) {
    $ps = $pdo->prepare('SELECT id, nome, data_inicio, data_fim FROM periodos_letivos WHERE ano_letivo_id=? ORDER BY data_inicio');
    $ps->execute([$a['id']]);
    $periodos_map[$a['id']] = $ps->fetchAll();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin • Períodos Letivos</title>
  <link rel="stylesheet" href="/adminfrequencia/admin.css">
  <?php $theme = $_GET['theme'] ?? ''; if ($theme==='light'){ ?>
    <link rel="stylesheet" href="/adminfrequencia/light.css">
  <?php } ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>
  <script src="/adminfrequencia/datepicker.js"></script>
  <script src="/adminfrequencia/modal.js"></script>
</head>
<body>
  <?php if (($theme ?? '')==='light'){ ?>
    <div class="header branded">
      <div class="row">
        <div class="brand-block">
          <?php $logo = $_SESSION['escola_logo'] ?? ''; $nome = $_SESSION['escola_nome'] ?? 'Escola'; $user = $_SESSION['user_name'] ?? 'Usuário'; ?>
          <img src="<?php echo $logo ?: 'https://via.placeholder.com/56x56.png?text=E'; ?>" alt="">
          <div>
            <div class="brand">Períodos • <?php echo htmlspecialchars($nome); ?></div>
            <div class="user">Usuário: <?php echo htmlspecialchars($user); ?></div>
          </div>
        </div>
        <span class="badge ok"><?php echo htmlspecialchars($msg); ?></span>
      </div>
      <div class="row"><a class="btn-secondary" href="?<?php echo $view_escola? 'escola='.$view_escola:''; ?>">Tema escuro</a></div>
    </div>
  <?php } else { ?>
    <div class="top"><div>Admin • Períodos Letivos</div><div class="muted"><?php echo htmlspecialchars($msg); ?></div></div>
  <?php } ?>
  <div class="layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div class="content">
      <div class="row" style="align-items:center;justify-content:space-between">
        <div class="muted">Escola: <?php echo $escola_atual ? htmlspecialchars($pdo->query('SELECT nome FROM escolas WHERE id='.(int)$escola_atual)->fetch()['nome'] ?? '') : ($session_escola===null?'selecione':'não definida'); ?> • Ano atual: <?php echo $ano_atual ? htmlspecialchars($pdo->query('SELECT ano FROM anos_letivos WHERE id='.(int)$ano_atual)->fetch()['ano'] ?? '') : 'não definido'; ?></div>
        <div style="display:flex;gap:8px;align-items:center">
          <?php if ($session_escola===null){ ?>
            <form method="get" class="row" style="gap:8px">
              <select name="escola" required>
                <option value="">Selecione a escola</option>
                <?php foreach ($schools as $sc){ ?>
                  <option value="<?php echo $sc['id']; ?>" <?php echo ((string)$escola_atual===(string)$sc['id'])?'selected':''; ?>><?php echo htmlspecialchars($sc['nome']); ?></option>
                <?php } ?>
              </select>
              <button type="submit">Filtrar</button>
            </form>
          <?php } ?>
          <button class="btn-secondary" type="button" onclick="openConfig()">Configurar Ano</button>
        </div>
      </div>
      <?php if (($theme ?? '')!=='light'){ ?>
        <div class="row" style="margin:10px 0"><a class="btn" href="?<?php echo $view_escola? 'escola='.$view_escola.'&':''; ?>theme=light">Preview tema claro</a></div>
      <?php } ?>
      <div class="modal-backdrop" id="modalConfig">
        <div class="modal">
          <div class="hd"><div>Configuração de Ano Letivo</div><button class="btn-secondary" type="button" onclick="closeConfig()">Fechar</button></div>
          <div class="bd">
            <div id="pane-ano">
              <div class="muted">Ano atual: <?php echo $ano_atual ? htmlspecialchars($pdo->query('SELECT ano FROM anos_letivos WHERE id='.(int)$ano_atual)->fetch()['ano'] ?? '') : 'nenhum'; ?></div>
              <form method="post" class="row">
                <input name="ano" type="number" placeholder="Ano (ex: 2025)" value="<?php echo date('Y'); ?>">
                <select name="status"><option value="ativo">ativo</option><option value="inativo">inativo</option></select>
                <?php if ($session_escola===null){ ?>
                  <input type="hidden" name="escola" value="<?php echo htmlspecialchars((string)$escola_atual); ?>">
                <?php } ?>
                <button name="act" value="create_ano">Criar Ano</button>
              </form>
              <form method="post" class="row">
                <select name="ano_letivo_id">
                  <?php foreach ($anos as $a){ ?>
                    <option value="<?php echo $a['id']; ?>" <?php echo ((string)$ano_atual === (string)$a['id'])?'selected':''; ?>><?php echo $a['ano']; ?></option>
                  <?php } ?>
                </select>
                <?php if ($session_escola===null){ ?>
                  <input type="hidden" name="escola" value="<?php echo htmlspecialchars((string)$escola_atual); ?>">
                <?php } ?>
                <button name="act" value="set_ano_atual">Definir Ano Atual</button>
              </form>
            </div>
          </div>
          <div class="ft"><button class="btn-secondary" type="button" onclick="closeConfig()">Concluir</button></div>
        </div>
      </div>
      <h2 class="title">Anos e Períodos</h2>
      <?php foreach ($anos as $a){ ?>
        <div class="row" style="align-items:center;justify-content:space-between">
          <div class="muted">Ano: <strong><?php echo $a['ano']; ?></strong> • Status: <?php echo $a['status']; ?> <?php if ((string)$ano_atual === (string)$a['id']){ echo '• Atual'; } ?></div>
          <div><button class="btn" type="button" onclick="openPeriod('<?php echo $a['id']; ?>')">Períodos Letivos</button></div>
        </div>
        <table>
          <thead><tr><th>Nome</th><th>Início</th><th>Fim</th></tr></thead>
          <tbody>
            <?php foreach ($periodos_map[$a['id']] as $p){ ?>
              <tr>
                <td><?php echo htmlspecialchars($p['nome']); ?></td>
                <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($p['data_inicio']))); ?></td>
                <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($p['data_fim']))); ?></td>
              </tr>
            <?php } ?>
            <?php if (empty($periodos_map[$a['id']])){ ?>
              <tr><td colspan="3" class="muted">Nenhum período cadastrado para este ano.</td></tr>
            <?php } ?>
          </tbody>
        </table>
        <div class="modal-backdrop" id="modal-periodo-<?php echo $a['id']; ?>">
          <div class="modal">
            <div class="hd"><div>Períodos Letivos • Ano <?php echo $a['ano']; ?></div><button class="btn-secondary" type="button" onclick="closePeriod('<?php echo $a['id']; ?>')">Fechar</button></div>
            <div class="bd">
              <table>
                <thead><tr><th>Nome</th><th>Início</th><th>Fim</th><th>Ações</th></tr></thead>
                <tbody>
                  <?php foreach ($periodos_map[$a['id']] as $p){ ?>
                  <tr>
                    <form method="post">
                      <td><input name="nome" value="<?php echo htmlspecialchars($p['nome']); ?>"></td>
                      <td><input name="data_inicio" class="date" type="text" value="<?php echo htmlspecialchars($p['data_inicio']); ?>"></td>
                      <td><input name="data_fim" class="date" type="text" value="<?php echo htmlspecialchars($p['data_fim']); ?>"></td>
                      <td style="white-space:nowrap;display:flex;gap:8px">
                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                        <button name="act" value="update_periodo">Salvar</button>
                        <button name="act" value="delete_periodo" onclick="return confirm('Excluir período?')">Excluir</button>
                      </td>
                    </form>
                  </tr>
                  <?php } ?>
                  <?php if (empty($periodos_map[$a['id']])){ ?>
                    <tr><td colspan="4" class="muted">Nenhum período cadastrado. Use o formulário abaixo para adicionar.</td></tr>
                  <?php } ?>
                </tbody>
              </table>
              <div class="muted" style="margin:8px 0">Adicionar novo período</div>
              <form method="post" class="row">
                <input type="hidden" name="ano_letivo_id" value="<?php echo $a['id']; ?>">
                <input name="nome" placeholder="Nome do período" required>
                <input name="data_inicio" class="date" type="text" placeholder="Início" required>
                <input name="data_fim" class="date" type="text" placeholder="Fim" required>
                <button class="btn" name="act" value="create_periodo">Adicionar</button>
              </form>
            </div>
            <div class="ft"><button class="btn-secondary" type="button" onclick="closePeriod('<?php echo $a['id']; ?>')">Concluir</button></div>
          </div>
        </div>
        <hr style="border:0;border-top:1px solid rgba(255,255,255,.08);margin:18px 0">
      <?php } ?>
      <?php if (!$anos){ ?>
        <div class="muted">Nenhum ano letivo. Use “Configurar contexto” para criar e definir.</div>
      <?php } ?>
    </div>
  </div>
</body>
</html>
