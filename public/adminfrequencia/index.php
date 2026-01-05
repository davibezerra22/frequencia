<?php
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Portal Administrativo • Login</title>
  <link rel="stylesheet" href="/adminfrequencia/light.css">
  <style>
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,sans-serif;color:var(--text)}
    .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{width:100%;max-width:420px;background:var(--surface);border-radius:16px;padding:28px;box-shadow:var(--card-shadow);border:1px solid var(--border)}
    .title{font-size:22px;margin:0 0 6px}
    .sub{font-size:14px;color:var(--muted);margin:0 0 18px}
    .field{margin:12px 0}
    label{display:block;font-size:13px;color:var(--muted);margin-bottom:8px}
    input{width:100%;padding:12px 14px;border-radius:10px;border:1px solid var(--border);background:#fff;color:var(--text);outline:none}
    input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(43,190,116,.18)}
    .btn{width:100%;padding:12px 14px;border:0;border-radius:10px;background:var(--primary);color:#fff;font-weight:600;cursor:pointer}
    .btn:disabled{opacity:.6;cursor:not-allowed}
    .status{margin-top:12px;font-size:13px}
    .status.ok{color:var(--success)}.status.err{color:var(--danger)}
    .brand{display:flex;align-items:center;gap:10px;margin-bottom:14px;font-weight:600}
    .hint{margin-top:14px;color:var(--muted);font-size:12px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="brand">Admin • Sistema de Frequência</div>
      <h1 class="title">Entrar</h1>
      <p class="sub">Acesso restrito à gestão e admin.</p>
      <?php
        $firstLink = '';
        try {
          $pdo = \App\Database\Connection::get();
          $q = $pdo->query('SELECT COUNT(*) AS c FROM usuarios')->fetch()['c'] ?? 0;
          if ((int)$q === 0) { $firstLink = '<a href="./first_user.php" style="color:#3b82f6;font-size:13px">Criar primeiro usuário</a>'; }
        } catch (\Throwable $e) { }
      ?>
      <form id="form" action="./authenticate.php" method="post" autocomplete="off" novalidate>
        <div class="field">
          <label for="usuario">Usuário</label>
          <input id="usuario" name="usuario" type="text" required autocomplete="username" />
        </div>
        <div class="field">
          <label for="senha">Senha</label>
          <input id="senha" name="senha" type="password" required autocomplete="current-password" />
        </div>
        <button class="btn" type="submit">Entrar</button>
        <div id="status" class="status"></div>
      </form>
      <p class="hint">Use suas credenciais cadastradas. <?php echo $firstLink; ?></p>
    </div>
  </div>
  <script>
    const form=document.getElementById('form');const status=document.getElementById('status');
    form.addEventListener('submit',async e=>{
      e.preventDefault();
      status.textContent='Verificando conexão...';status.className='status';
      const fd=new FormData(form);
      const r=await fetch(form.action,{method:'POST',body:fd});
      const j=await r.json();
      if(j.status==='ok'){status.textContent='Login realizado. Redirecionando...';status.className='status ok';location.href='/adminfrequencia/dashboard.php'}
      else{status.textContent=j.message||'Falha no login';status.className='status err'}
    });
  </script>
</body>
</html>
