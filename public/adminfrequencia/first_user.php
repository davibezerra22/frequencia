<?php
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
use App\Database\Connection;
session_start();
$pdo = Connection::get();
$has = (int)$pdo->query('SELECT COUNT(*) AS c FROM usuarios')->fetch()['c'] ?? 0;
if ($has > 0) { header('Location: /adminfrequencia/'); exit; }
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome = trim($_POST['nome'] ?? '');
  $usuario = trim($_POST['usuario'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $senha = $_POST['senha'] ?? '';
  $conf = $_POST['conf'] ?? '';
  if ($nome !== '' && $usuario !== '' && $senha !== '' && $senha === $conf) {
    $hash = password_hash($senha, PASSWORD_BCRYPT);
    $s = $pdo->prepare('INSERT INTO usuarios (nome, usuario, email, senha_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)');
    try {
      $s->execute([$nome, $usuario, $email ?: null, $hash, 'admin', 'ativo']);
      $_SESSION['user_id'] = (int)$pdo->lastInsertId();
      $_SESSION['user_name'] = $nome;
      $_SESSION['user_role'] = 'admin';
      header('Location: /adminfrequencia/dashboard.php'); exit;
    } catch (\Throwable $e) { $msg = 'Erro ao criar usuário'; }
  } else { $msg = 'Dados inválidos ou senha não confere'; }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin • Primeiro Usuário</title>
  <style>
    :root{--bg:#0b0f1a;--card:#121826;--text:#e5e7eb;--muted:#9ca3af;--accent:#3b82f6}
    *{box-sizing:border-box}body{margin:0;background:#0f172a;color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,sans-serif}
    .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{width:100%;max-width:480px;background:#121826;border-radius:16px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.06)}
    .title{font-size:22px;margin:0 0 6px}
    .sub{font-size:14px;color:var(--muted);margin:0 0 18px}
    .field{margin:12px 0}
    label{display:block;font-size:13px;color:var(--muted);margin-bottom:8px}
    input{width:100%;padding:12px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.12);background:#0b1220;color:var(--text);outline:none}
    input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(59,130,246,.15)}
    .btn{width:100%;padding:12px 14px;border:0;border-radius:10px;background:var(--accent);color:white;font-weight:600;cursor:pointer}
    .status{margin-top:12px;font-size:13px;color:#ef4444}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1 class="title">Criar Primeiro Usuário</h1>
      <p class="sub">Não há usuários cadastrados. Crie o administrador inicial.</p>
      <form method="post">
        <div class="field"><label>Nome</label><input name="nome" required></div>
        <div class="field"><label>Usuário</label><input name="usuario" required></div>
        <div class="field"><label>Email</label><input name="email" type="email"></div>
        <div class="field"><label>Senha</label><input name="senha" type="password" required></div>
        <div class="field"><label>Confirmar Senha</label><input name="conf" type="password" required></div>
        <button class="btn" type="submit">Criar</button>
        <div class="status"><?php echo htmlspecialchars($msg); ?></div>
      </form>
    </div>
  </div>
</body>
</html>
