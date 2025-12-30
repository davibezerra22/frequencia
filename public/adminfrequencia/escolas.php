<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
use App\Database\Connection;
$pdo = Connection::get();
$msg = '';
$is_super = (!isset($_SESSION['escola_id']) || $_SESSION['escola_id'] === null) && ($_SESSION['user_role'] ?? '') === 'admin';
if (!$is_super) { header('Location: /adminfrequencia/dashboard.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['act'] ?? '';
  if ($act === 'create_school') {
    $nome = trim($_POST['nome'] ?? '');
    $logo = trim($_POST['logotipo'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $s = $pdo->prepare('INSERT INTO escolas (nome, logotipo, slug, status) VALUES (?, ?, ?, ?)');
    try {
      $s->execute([$nome, $logo ?: null, $slug ?: null, 'ativo']);
      $eid_new = (int)$pdo->lastInsertId();
      $admin_nome = trim($_POST['admin_nome'] ?? '');
      $admin_usuario = trim($_POST['admin_usuario'] ?? '');
      $admin_email = trim($_POST['admin_email'] ?? '');
      $admin_senha = $_POST['admin_senha'] ?? '';
      $admin_conf = $_POST['admin_conf'] ?? '';
      if ($admin_nome && $admin_usuario && $admin_senha && $admin_senha === $admin_conf) {
        $hash = password_hash($admin_senha, PASSWORD_BCRYPT);
        $insU = $pdo->prepare('INSERT INTO usuarios (nome, usuario, email, senha_hash, role, status, escola_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
        try { $insU->execute([$admin_nome, $admin_usuario, $admin_email ?: null, $hash, 'admin', 'ativo', $eid_new]); } catch (\Throwable $e) {}
      }
      $msg = 'Escola criada';
    } catch (\Throwable $e) { $msg = 'Erro ao criar escola'; }
  } elseif ($act === 'update_school') {
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $logo = trim($_POST['logotipo'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $status = $_POST['status'] ?? 'ativo';
    $s = $pdo->prepare('UPDATE escolas SET nome=?, logotipo=?, slug=?, status=? WHERE id=?');
    try { $s->execute([$nome, $logo ?: null, $slug ?: null, $status, $id]); $msg = 'Escola atualizada'; } catch (\Throwable $e) { $msg = 'Erro ao atualizar escola'; }
  }
}
$escolas = $pdo->query('SELECT id, nome, logotipo, slug, status FROM escolas ORDER BY nome')->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Superadmin • Escolas</title>
  <link rel="stylesheet" href="/adminfrequencia/admin.css">
</head>
<body>
  <div class="top"><div>Superadmin • Escolas</div><div class="muted"><?php echo htmlspecialchars($msg); ?></div></div>
  <div class="layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div class="content">
      <div class="row" style="align-items:center;justify-content:space-between">
        <h2 class="title" style="margin:0">Escolas</h2>
        <button class="btn" type="button" onclick="openSchool()">Nova Escola</button>
      </div>
      <div class="modal-backdrop" id="modalSchool">
        <div class="modal">
          <div class="hd"><div>Nova Escola</div><button class="btn-secondary" type="button" onclick="closeSchool()">Fechar</button></div>
          <div class="bd">
            <form method="post" class="row">
              <input name="nome" placeholder="Nome da Escola" required>
              <input name="logotipo" placeholder="URL do Logotipo (opcional)">
              <input name="slug" placeholder="Slug (opcional)">
              <input name="admin_nome" placeholder="Nome do Admin" required>
              <input name="admin_usuario" placeholder="Login do Admin" required>
              <input name="admin_email" placeholder="Email do Admin (opcional)">
              <input name="admin_senha" type="password" placeholder="Senha do Admin" required>
              <input name="admin_conf" type="password" placeholder="Confirmar Senha" required>
              <button class="btn" name="act" value="create_school">Criar</button>
            </form>
          </div>
          <div class="ft"><button class="btn-secondary" type="button" onclick="closeSchool()">Concluir</button></div>
        </div>
      </div>
      <h2 class="title">Lista de Escolas</h2>
      <table>
        <thead><tr><th>Logo</th><th>Nome</th><th>Slug</th><th>Status</th><th>Ações</th></tr></thead>
        <tbody>
          <?php foreach ($escolas as $e){ ?>
            <tr>
              <form method="post" class="row" style="gap:8px">
                <td><?php if ($e['logotipo']){ ?><img src="<?php echo htmlspecialchars($e['logotipo']); ?>" alt="" style="height:28px"><?php } ?></td>
                <td><input name="nome" value="<?php echo htmlspecialchars($e['nome']); ?>"></td>
                <td><input name="slug" value="<?php echo htmlspecialchars((string)$e['slug']); ?>"></td>
                <td>
                  <select name="status">
                    <option value="ativo" <?php echo $e['status']==='ativo'?'selected':''; ?>>ativo</option>
                    <option value="inativo" <?php echo $e['status']==='inativo'?'selected':''; ?>>inativo</option>
                  </select>
                </td>
                <td style="white-space:nowrap;display:flex;gap:8px">
                  <input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                  <input name="logotipo" value="<?php echo htmlspecialchars((string)$e['logotipo']); ?>" placeholder="Logo URL">
                  <button name="act" value="update_school">Salvar</button>
                </td>
              </form>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
  <script src="/adminfrequencia/modal.js"></script>
</body>
</html>
