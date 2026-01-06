<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
use App\Database\Connection;
$pdo = Connection::get();
$msg = '';
$role = $_SESSION['user_role'] ?? '';
$eid = $_SESSION['escola_id'] ?? null;
$is_admin = ($role === 'admin');
if (!$is_admin) { header('Location: /adminfrequencia/dashboard.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['act'] ?? '';
  if ($act === 'create_user') {
    $nome = trim($_POST['nome'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $conf = $_POST['conf'] ?? '';
    $roleN = $_POST['role'] ?? 'gestao';
    $status = $_POST['status'] ?? 'ativo';
    $escola_id = ($eid ?? null);
    if ($eid === null) { $escola_id = (int)($_POST['escola_id'] ?? 0) ?: null; }
    if ($nome && $usuario && $senha && $senha === $conf) {
      $hash = password_hash($senha, PASSWORD_BCRYPT);
      $s = $pdo->prepare('INSERT INTO usuarios (nome, usuario, email, senha_hash, role, status, escola_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
      try { $s->execute([$nome, $usuario, $email ?: null, $hash, $roleN, $status, $escola_id]); $msg = 'Usuário criado'; } catch (\Throwable $e) { $msg = 'Erro ao criar usuário'; }
    } else { $msg = 'Dados inválidos ou senha não confere'; }
  } elseif ($act === 'update_user') {
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $roleN = $_POST['role'] ?? 'gestao';
    $status = $_POST['status'] ?? 'ativo';
    $escola_id = ($eid ?? null);
    if ($eid === null) { $escola_id = (int)($_POST['escola_id'] ?? 0) ?: null; }
    $s = $pdo->prepare('UPDATE usuarios SET nome=?, email=?, role=?, status=?, escola_id=? WHERE id=?');
    try { $s->execute([$nome, $email ?: null, $roleN, $status, $escola_id, $id]); $msg = 'Usuário atualizado'; } catch (\Throwable $e) { $msg = 'Erro ao atualizar usuário'; }
  } elseif ($act === 'reset_password') {
    $id = (int)($_POST['id'] ?? 0);
    $senha = $_POST['senha'] ?? '';
    $conf = $_POST['conf'] ?? '';
    if ($senha && $senha === $conf) {
      $hash = password_hash($senha, PASSWORD_BCRYPT);
      $s = $pdo->prepare('UPDATE usuarios SET senha_hash=? WHERE id=?');
      try { $s->execute([$hash, $id]); $msg = 'Senha atualizada'; } catch (\Throwable $e) { $msg = 'Erro ao atualizar senha'; }
    } else { $msg = 'Senha inválida'; }
  } elseif ($act === 'delete_user') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id === ($_SESSION['user_id'] ?? 0)) { $msg = 'Não é possível excluir seu próprio usuário'; }
    else {
      try { $pdo->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]); $msg = 'Usuário excluído'; } catch (\Throwable $e) { $msg = 'Erro ao excluir usuário'; }
    }
  }
}
$schools = [];
if ($eid === null) { $schools = $pdo->query('SELECT id, nome FROM escolas ORDER BY nome')->fetchAll(); }
$users_stmt = ($eid === null)
  ? $pdo->prepare('SELECT u.id, u.nome, u.usuario, u.email, u.role, u.status, u.escola_id, e.nome AS escola FROM usuarios u LEFT JOIN escolas e ON e.id=u.escola_id ORDER BY e.nome, u.nome')
  : $pdo->prepare('SELECT u.id, u.nome, u.usuario, u.email, u.role, u.status, u.escola_id, NULL AS escola FROM usuarios u WHERE u.escola_id=? ORDER BY u.nome');
if ($eid === null) { $users_stmt->execute(); } else { $users_stmt->execute([$eid]); }
$users = $users_stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin • Usuários</title>
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
    <div class="top"><div>Admin • Usuários</div><div class="muted"><?php echo htmlspecialchars($msg); ?></div></div>
    <div class="layout">
      <?php require __DIR__ . '/_sidebar.php'; ?>
      <div class="content">
        <div class="row" style="margin:10px 0"><a class="btn" href="?">Tema claro</a></div>
      </div>
    </div>
    </body>
    </html>
    <?php return; ?>
  <?php } ?>
  <div class="layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div class="content">
      <div class="row" style="align-items:center;justify-content:space-between">
        <h2 class="title" style="margin:0">Usuários</h2>
        <button class="btn" type="button" onclick="openUser()">Novo Usuário</button>
      </div>
      <div class="modal-backdrop" id="modalUser">
        <div class="modal">
          <div class="hd"><div>Novo Usuário</div><button class="btn-secondary" type="button" onclick="closeUser()">Fechar</button></div>
          <div class="bd">
            <form method="post" class="form-grid">
              <div class="field">
                <label>Nome</label>
                <input name="nome" required>
              </div>
              <div class="field">
                <label>Login</label>
                <input name="usuario" required>
              </div>
              <div class="field">
                <label>Email (opcional)</label>
                <input name="email">
              </div>
              <div class="field">
                <label>Senha</label>
                <input name="senha" type="password" required>
              </div>
              <div class="field">
                <label>Confirmar Senha</label>
                <input name="conf" type="password" required>
              </div>
              <div class="field">
                <label>Role</label>
                <select name="role">
                  <option value="admin">admin</option>
                  <option value="gestao">gestao</option>
                  <option value="operador">operador</option>
                </select>
              </div>
              <div class="field">
                <label>Status</label>
                <select name="status">
                  <option value="ativo">ativo</option>
                  <option value="inativo">inativo</option>
                </select>
              </div>
              <?php if ($eid === null){ ?>
                <div class="field span-2">
                  <label>Escola</label>
                  <select name="escola_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($schools as $s){ ?>
                      <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nome']); ?></option>
                    <?php } ?>
                  </select>
                </div>
              <?php } ?>
              <div class="span-2 actions">
                <button class="btn" name="act" value="create_user">Criar</button>
              </div>
            </form>
          </div>
          <div class="ft"><button class="btn-secondary" type="button" onclick="closeUser()">Concluir</button></div>
        </div>
      </div>
      <h2 class="title">Lista de Usuários</h2>
      <table>
        <thead><tr><th>Nome</th><th>Login</th><th>Email</th><th>Role</th><th>Status</th><?php if ($eid===null){ ?><th>Escola</th><?php } ?><th>Ações</th></tr></thead>
        <tbody>
          <?php foreach ($users as $u){ ?>
            <tr>
              <form method="post">
                <td><input name="nome" value="<?php echo htmlspecialchars($u['nome']); ?>"></td>
                <td><?php echo htmlspecialchars($u['usuario']); ?></td>
                <td><input name="email" value="<?php echo htmlspecialchars((string)$u['email']); ?>"></td>
                <td>
                  <select name="role">
                    <option value="admin" <?php echo $u['role']==='admin'?'selected':''; ?>>admin</option>
                    <option value="gestao" <?php echo $u['role']==='gestao'?'selected':''; ?>>gestao</option>
                    <option value="operador" <?php echo $u['role']==='operador'?'selected':''; ?>>operador</option>
                  </select>
                </td>
                <td>
                  <select name="status">
                    <option value="ativo" <?php echo $u['status']==='ativo'?'selected':''; ?>>ativo</option>
                    <option value="inativo" <?php echo $u['status']==='inativo'?'selected':''; ?>>inativo</option>
                  </select>
                </td>
                <?php if ($eid===null){ ?>
                  <td>
                    <select name="escola_id">
                      <option value="">(nenhuma)</option>
                      <?php foreach ($schools as $s){ ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo ((string)$u['escola_id']===(string)$s['id'])?'selected':''; ?>><?php echo htmlspecialchars($s['nome']); ?></option>
                      <?php } ?>
                    </select>
                  </td>
                <?php } ?>
                <td style="white-space:nowrap;display:flex;gap:8px">
                  <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                  <button name="act" value="update_user">Salvar</button>
                  <button name="act" value="delete_user" onclick="return confirm('Excluir usuário?')">Excluir</button>
                  <button type="button" onclick="openReset('<?php echo $u['id']; ?>','<?php echo htmlspecialchars($u['nome']); ?>')">Atualizar Senha</button>
                </td>
              </form>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
  <script src="/adminfrequencia/modal.js"></script>
  <div class="modal-backdrop" id="modalReset">
    <div class="modal">
      <div class="hd"><div>Atualizar Senha</div><button class="btn-secondary" type="button" onclick="closeReset()">Fechar</button></div>
      <div class="bd">
        <div class="muted" id="reset_user_name"></div>
        <form method="post" class="row">
          <input type="hidden" name="id" id="reset_user_id">
          <input name="senha" type="password" placeholder="Nova senha" required>
          <input name="conf" type="password" placeholder="Confirmar senha" required>
          <button class="btn" name="act" value="reset_password">Atualizar Senha</button>
        </form>
      </div>
      <div class="ft"><button class="btn-secondary" type="button" onclick="closeReset()">Concluir</button></div>
    </div>
  </div>
  <script>
    function openReset(id,name){document.getElementById('modalReset').style.display='flex';document.getElementById('reset_user_id').value=id;document.getElementById('reset_user_name').textContent=name}
    function closeReset(){document.getElementById('modalReset').style.display='none'}
  </script>
</body>
</html>
