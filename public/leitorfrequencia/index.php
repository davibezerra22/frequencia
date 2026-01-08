<?php
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Support/Session.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
App\Support\Session::start();
if (!isset($_SESSION['user_id'])) { header('Location: /leitorfrequencia/login.php'); exit; }
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Totem de Leitura</title>
  <link rel="stylesheet" href="/adminfrequencia/admin.css">
  <link rel="stylesheet" href="/adminfrequencia/light.css">
  <style>
    *{box-sizing:border-box}
    body{margin:0}
    .layout{display:grid;grid-template-columns:1fr;gap:16px}
    .container{max-width:1200px;margin:0 auto;padding:16px}
    .panel{background:var(--surface);border:1px solid var(--border);border-radius:12px}
    .header{display:flex;align-items:center;justify-content:space-between}
    .brand-block{display:flex;align-items:center;gap:12px}
    .brand-block img{width:40px;height:40px;border-radius:8px}
    .scan{display:flex;align-items:center;justify-content:center;height:68vh}
    #reader{width:100%;max-width:520px;aspect-ratio:1/1}
    #reader video, #reader canvas{width:100% !important;height:100% !important;object-fit:cover;border-radius:12px}
    .scan { position: relative }
    #camPrompt{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.35);color:#fff;font-weight:600;border-radius:12px;cursor:pointer}
    #camPrompt.hidden{display:none}
    .content{display:grid;grid-template-columns:1.2fr 0.8fr;gap:16px}
    @media (max-width:900px){ .content{grid-template-columns:1fr} .scan{height:52vh} }
    .info{padding:16px}
    .student{display:flex;align-items:center;gap:12px}
    .student img{width:96px;height:96px;border-radius:12px;object-fit:cover;background:#EEF2F7}
    .name{font-size:20px;font-weight:600}
    .line{height:1px;background:var(--border);margin:12px 0}
    .status{display:flex;align-items:center;gap:8px}
    .badge{padding:4px 10px;border-radius:999px;font-size:13px;border:1px solid var(--border)}
    .badge.ok{color:#10b981;border-color:#10b981}
    .badge.dup{color:#f59e0b;border-color:#f59e0b}
    .badge.err{color:#ef4444;border-color:#ef4444}
    .msg{color:var(--muted);font-size:14px}
    .actions{display:flex;gap:8px;margin-top:12px}
    .manual{display:flex;gap:8px;margin-top:12px}
    .inp{flex:1;background:#FFFFFF;color:#111827;border:1px solid var(--border);border-radius:8px;padding:8px}
  </style>
</head>
<body>
  <div class="layout">
    <div class="container">
      <div class="panel" style="padding:16px">
        <div class="header branded">
          <div class="brand-block">
            <img src="/adminfrequencia/avatar.svg" alt="">
            <div>
              <div class="brand">Totem de Leitura</div>
              <div class="muted">Dispositivo: <span id="deviceLabel"></span></div>
            </div>
          </div>
          <div class="actions" style="display:flex;gap:8px">
            <a class="btn-secondary" id="changeDevice">Trocar ID</a>
            <select class="inp" id="camSel" style="max-width:280px"></select>
            <button class="btn-secondary" id="refreshCams">Atualizar câmeras</button>
          </div>
        </div>
        <audio id="sndOk" src="/leitorfrequencia/beep.php?type=ok" preload="auto" playsinline></audio>
        <audio id="sndErr" src="/leitorfrequencia/beep.php?type=err" preload="auto" playsinline></audio>
      </div>
      <div class="content">
        <div class="panel scan">
          <div id="camPrompt" class="">Toque para iniciar a câmera</div>
          <div id="reader"></div>
        </div>
        <div class="panel">
          <div class="info">
            <div class="student">
              <img id="foto" src="/adminfrequencia/avatar.svg" alt="">
              <div>
                <div class="name" id="nome"></div>
                <div class="msg" id="turma"></div>
              </div>
            </div>
            <div class="line"></div>
            <div class="status">
              <div class="badge" id="stBadge">Aguardando leitura</div>
            </div>
            <div class="msg" id="mensagem"></div>
            <div class="actions">
              <button class="btn-secondary" id="resetBtn">Reiniciar leitor</button>
            </div>
            <div class="manual" id="manualBox" style="display:none">
              <input class="inp" id="manualInput" placeholder="Cole o QR aqui">
              <button class="btn" id="manualSend">Enviar</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    var sndOk = null, sndErr = null, primedOk = false, primedErr = false;
    function primeAudio(){
      try {
        if (!sndOk || !sndErr){
          sndOk = document.getElementById('sndOk');
          sndErr = document.getElementById('sndErr');
        }
        if (sndOk && !primedOk){
          sndOk.muted = true; sndOk.volume = 0; sndOk.load();
          sndOk.play().then(function(){ sndOk.pause(); sndOk.currentTime=0; sndOk.muted=false; sndOk.volume=1; primedOk=true; }).catch(function(){});
        }
        if (sndErr && !primedErr){
          sndErr.muted = true; sndErr.volume = 0; sndErr.load();
          sndErr.play().then(function(){ sndErr.pause(); sndErr.currentTime=0; sndErr.muted=false; sndErr.volume=1; primedErr=true; }).catch(function(){});
        }
      } catch(e){}
    }
    function randId(){ return 'TOT-'+Math.floor(Math.random()*1e9).toString(36).toUpperCase(); }
    var deviceId = localStorage.getItem('totem_device_id') || randId();
    localStorage.setItem('totem_device_id', deviceId);
    document.getElementById('deviceLabel').textContent = deviceId;
    document.getElementById('changeDevice').onclick = function(){
      deviceId = randId(); localStorage.setItem('totem_device_id', deviceId);
      document.getElementById('deviceLabel').textContent = deviceId;
    };
    function setStatus(st,msg){
      var b=document.getElementById('stBadge'); var m=document.getElementById('mensagem');
      b.className='badge'; m.textContent=msg||'';
      if (st==='ok'){ b.classList.add('ok'); b.textContent='Presença registrada'; }
      else if (st==='duplicada'){ b.classList.add('dup'); b.textContent='Frequencia já registrada'; }
      else if (st==='fora_contexto'){ b.classList.add('err'); b.textContent='Fora de contexto'; }
      else if (st==='erro'){ b.classList.add('err'); b.textContent='Erro'; }
      else { b.textContent='Aguardando leitura'; }
    }
    function resetBadge(){
      var b=document.getElementById('stBadge'); var m=document.getElementById('mensagem');
      b.className='badge';
      b.textContent='Aguardando leitura';
      m.textContent='';
    }
    // Handlers de bip removidos
    function setAluno(a,serie,turma){
      document.getElementById('nome').textContent = a?.nome || '';
      document.getElementById('turma').textContent = ((serie||'')+' • '+(turma||'')).trim();
      var src = a?.foto || '/adminfrequencia/avatar.svg';
      var img=document.getElementById('foto');
      img.src=src; img.onerror=function(){ img.src='/adminfrequencia/avatar.svg'; };
    }
    function resetAluno(){
      document.getElementById('nome').textContent = '';
      document.getElementById('turma').textContent = '';
      var img=document.getElementById('foto');
      img.src='/adminfrequencia/avatar.svg';
    }
    function ensureHtml5(cb){
      if (window.Html5Qrcode){ cb(); return; }
      var s=document.createElement('script');
      s.src='/leitorfrequencia/html5-qrcode.min.js';
      s.onload=function(){ cb(); };
      s.onerror=function(){
        var c=document.createElement('script');
        c.src='https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.9/minified/html5-qrcode.min.js';
        c.onload=function(){ cb(); };
        c.onerror=function(){
          var u=document.createElement('script');
          u.src='https://unpkg.com/html5-qrcode@2.3.9/minified/html5-qrcode.min.js';
          u.onload=function(){ cb(); };
          u.onerror=function(){ cb(); };
          document.head.appendChild(u);
        };
        document.head.appendChild(c);
      };
      document.head.appendChild(s);
    }
    var scanner=null, running=false, paused=false, busy=false, startPending=false, resetTimeout=null, camSel=document.getElementById('camSel'), permissionGranted=false;
    function isIOS(){ return /iPhone|iPad|iPod/i.test(navigator.userAgent||''); }
    function loadCams(){
      if (!window.Html5Qrcode) return;
      Html5Qrcode.getCameras().then(function(devices){
        camSel.innerHTML='';
        devices.forEach(function(d,i){
          var opt=document.createElement('option'); opt.value=d.id; opt.textContent=(d.label||('Camera '+(i+1)));
          camSel.appendChild(opt);
        });
      }).catch(function(){});
    }
    async function requestPermissionDesktop(){
      try {
        if (permissionGranted || isIOS()) return;
        const stream = await navigator.mediaDevices.getUserMedia({ video: true });
        stream.getTracks().forEach(t=>t.stop());
        permissionGranted = true;
      } catch(e){}
    }
    function startScanner(){
      if (!window.Html5Qrcode){ setStatus('', 'Biblioteca de leitura não disponível'); setupManual(); return; }
      var el=document.getElementById('reader');
      el.innerHTML='';
      scanner = new Html5Qrcode('reader');
      var cfg={fps:25, qrbox: {width: 320, height: 320}, aspectRatio: 1.0, videoConstraints: { facingMode: { ideal: 'environment' } }};
      if (paused) { try { scanner.resume(); paused=false; running=true; return; } catch(e){} }
      paused=false; running=false;
      if (startPending || running) return;
      startPending=true;
      var dev = (!isIOS() && camSel && camSel.value) ? camSel.value : null;
      var startArg = dev ? dev : { facingMode: 'environment' };
      scanner.start(startArg, cfg, onScan, onFail).then(function(){
        running=true; startPending=false; document.getElementById('camPrompt').classList.add('hidden');
      }).catch(function(){
        scanner.start({ facingMode: { exact: 'environment' } }, cfg, onScan, onFail).then(function(){
          running=true; startPending=false; document.getElementById('camPrompt').classList.add('hidden');
        }).catch(function(){
          startPending=false; running=false;
          try { stopScanner(); } catch(e){}
          document.getElementById('camPrompt').classList.remove('hidden');
          document.getElementById('camPrompt').textContent='Toque para tentar novamente';
          setStatus('erro','Não foi possível abrir a câmera');
        });
      });
    }
    function pauseScanner(){
      if (!scanner || paused) return;
      try { scanner.pause(true); paused=true; } catch(e){ /* fallback abaixo */ 
        try { scanner.stop().then(function(){ paused=false; running=false; }).catch(function(){ running=false; }); } catch(e2){}
      }
    }
    function resumeScanner(){
      if (!scanner) return;
      try { scanner.resume(); paused=false; } catch(e){
        // fallback: restart
        try { stopScanner(); setTimeout(startScanner, 500); } catch(e2){}
      }
    }
    function stopScanner(){
      if (!scanner) return;
      try {
        scanner.stop().then(function(){ scanner.clear(); running=false; paused=false; }).catch(function(){ running=false; paused=false; });
      } catch(e){ running=false; paused=false; }
    }
    function setupManual(){
      var m=document.getElementById('manualBox');
      m.style.display='flex';
      document.getElementById('manualSend').onclick=function(){
        var v=document.getElementById('manualInput').value.trim();
        if (!v) return;
        onScan(v);
      };
    }
    async function onScan(text){
      if (busy) return;
      busy=true;
      pauseScanner();
      fetch('/api/frequencia/leitura.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({ qr:text, device_id: deviceId })
      }).then(function(r){ return r.json(); }).then(async function(j){
        setAluno(j.aluno||{}, j.serie||'', j.turma||'');
        setStatus(j.status||'', j.mensagem||'');
        primeAudio();
        if (j.status==='ok'){
          if (navigator.vibrate) navigator.vibrate(30);
          if (!sndOk) sndOk=document.getElementById('sndOk');
          if (sndOk){
            if (!primedOk) { try { sndOk.volume=0; await sndOk.play(); sndOk.pause(); sndOk.currentTime=0; sndOk.volume=1; primedOk=true; } catch(e){} }
            sndOk.currentTime=0;
            sndOk.play().catch(function(){ try { new Audio('/leitorfrequencia/beep.php?type=ok').play(); } catch(e){} });
          }
        } else {
          if (navigator.vibrate) navigator.vibrate([40,40]);
          if (!sndErr) sndErr=document.getElementById('sndErr');
          if (sndErr){
            if (!primedErr) { try { sndErr.volume=0; await sndErr.play(); sndErr.pause(); sndErr.currentTime=0; sndErr.volume=1; primedErr=true; } catch(e){} }
            sndErr.currentTime=0;
            sndErr.play().catch(function(){ try { new Audio('/leitorfrequencia/beep.php?type=err').play(); } catch(e){} });
          }
        }
      }).catch(async function(){
        setStatus('erro','Falha na API');
        primeAudio();
        if (!sndErr) sndErr=document.getElementById('sndErr');
        if (sndErr){
          if (!primedErr) { try { sndErr.volume=0; await sndErr.play(); sndErr.pause(); sndErr.currentTime=0; sndErr.volume=1; primedErr=true; } catch(e){} }
          sndErr.currentTime=0;
          sndErr.play().catch(function(){ try { new Audio('/leitorfrequencia/beep.php?type=err').play(); } catch(e){} });
        }
      }).finally(function(){
        clearTimeout(resetTimeout);
        resetTimeout=setTimeout(function(){ resetBadge(); resetAluno(); }, 5000);
        setTimeout(function(){ resumeScanner(); busy=false; }, 1400);
      });
    }
    function onFail(err){}
    document.getElementById('resetBtn').onclick=function(){ stopScanner(); setTimeout(startScanner,800); };
    document.getElementById('refreshCams').onclick=async function(){ if (!isIOS()) { await requestPermissionDesktop(); loadCams(); } };
    camSel.onchange=function(){ if (!isIOS()) { stopScanner(); setTimeout(startScanner,500); } };
    window.addEventListener('touchstart', async function(){ primeAudio(); if (!running && window.Html5Qrcode) { if (!isIOS()) await requestPermissionDesktop(); startScanner(); } }, {once:true});
    window.addEventListener('click', async function(){ primeAudio(); if (!running && window.Html5Qrcode) { if (!isIOS()) await requestPermissionDesktop(); startScanner(); } }, {once:true});
    document.getElementById('camPrompt').onclick = async function(){ primeAudio(); if (!isIOS()) await requestPermissionDesktop(); startScanner(); };
    ensureHtml5(function(){
      if (window.Html5Qrcode){
        if (isIOS()){
          document.getElementById('camSel').style.display='none';
          document.getElementById('refreshCams').style.display='none';
          startScanner();
        } else {
          requestPermissionDesktop().finally(function(){ loadCams(); startScanner(); });
        }
      } else { setupManual(); }
    });
  </script>
</body>
</html>
