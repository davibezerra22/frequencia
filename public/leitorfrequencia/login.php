<?php
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Support/Session.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
App\Support\Session::start();
if (isset($_SESSION['user_id'])) { header('Location: /leitorfrequencia/'); exit; }
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Totem • Login</title>
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
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1 class="title">Entrar no Totem</h1>
      <p class="sub">Use as mesmas credenciais do administrativo.</p>
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
    </div>
  </div>
  <script>
    const form=document.getElementById('form');const status=document.getElementById('status');
    form.addEventListener('submit',async e=>{
      e.preventDefault(); status.textContent='Verificando...'; status.className='status';
      const fd=new FormData(form);
      try{
        const r=await fetch(form.action,{method:'POST',body:fd});
        const j=await r.json();
        if(j.status==='ok'){ status.textContent='Login ok'; status.className='status ok'; location.href='/leitorfrequencia/'; }
        else{ status.textContent=j.message||'Falha no login'; status.className='status err'; }
      } catch(err){ status.textContent='Falha de rede'; status.className='status err'; }
    });
  </script>
</body>
</html>
