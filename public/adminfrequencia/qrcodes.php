<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
require_once __DIR__ . '/../../src/Support/ShortCode.php';
use App\Database\Connection;
use App\Support\ShortCode;
use App\Support\Env;
$pdo = Connection::get();
$msg = '';
$eid = $_SESSION['escola_id'] ?? null;
$schools = [];
if ($eid === null) { $schools = $pdo->query('SELECT id, nome FROM escolas ORDER BY nome')->fetchAll(); }
$school_view = $eid ?: (int)($_GET['escola'] ?? 0) ?: null;
$turmas = [];
if ($school_view) {
  $anos = $pdo->prepare('SELECT valor FROM configuracoes WHERE chave=\'ano_letivo_atual_id\' AND escola_id=?');
  $anos->execute([$school_view]); $ano_atual = (int)($anos->fetch()['valor'] ?? 0);
  if ($ano_atual) {
    $st = $pdo->prepare('SELECT t.id, t.nome, s.nome AS serie FROM turmas t JOIN series s ON s.id=t.serie_id WHERE t.ano_letivo_id=? ORDER BY s.nome, t.nome');
    $st->execute([$ano_atual]); $turmas = $st->fetchAll();
  }
}
$alunos = [];
$mode = $_GET['mode'] ?? 'turma';
$tid = (int)($_GET['turma'] ?? 0);
$aid = (int)($_GET['aluno'] ?? 0);
$secret = Env::get('QR_SECRET','dev-secret');
$escola_nome = $_SESSION['escola_nome'] ?? 'Escola';
$escola_logo = $_SESSION['escola_logo'] ?? '';
if ($school_view && ($eid===null)) {
  try {
    $sr = $pdo->prepare('SELECT nome, logotipo FROM escolas WHERE id=?'); $sr->execute([$school_view]);
    $sro = $sr->fetch(); if ($sro){ $escola_nome = (string)$sro['nome']; $escola_logo = (string)$sro['logotipo']; }
  } catch (\Throwable $e) {}
}
if ($mode==='turma' && $tid>0) {
  $q = $pdo->prepare('SELECT a.id,a.nome,a.matricula,s.nome AS serie,t.nome AS turma FROM matriculas_turma mt JOIN alunos a ON a.id=mt.aluno_id JOIN turmas t ON t.id=mt.turma_id JOIN series s ON s.id=t.serie_id WHERE mt.turma_id=? ORDER BY a.nome');
  $q->execute([$tid]); $alunos = $q->fetchAll();
} elseif ($mode==='aluno' && $aid>0) {
  $ano_stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id' AND escola_id=?"); 
  if ($school_view){ $ano_stmt->execute([$school_view]); $ano_atual = (int)($ano_stmt->fetch()['valor'] ?? 0); } else { $ano_atual = 0; }
  $q = $pdo->prepare('SELECT a.id,a.nome,a.matricula,s.nome AS serie,t.nome AS turma FROM alunos a LEFT JOIN matriculas_turma mt ON mt.aluno_id=a.id LEFT JOIN turmas t ON t.id=mt.turma_id LEFT JOIN series s ON s.id=t.serie_id WHERE a.id=?'.($ano_atual? ' AND t.ano_letivo_id='.$ano_atual:'').' LIMIT 1');
  $q->execute([$aid]); $row = $q->fetch(); $alunos = $row? [$row] : [];
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin • QR Codes</title>
  <link rel="stylesheet" href="/adminfrequencia/admin.css">
  <?php $theme = $_GET['theme'] ?? ''; if ($theme!=='dark'){ ?>
    <link rel="stylesheet" href="/adminfrequencia/light.css">
  <?php } ?>
  <style>
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
    .card{padding:12px;border:1px solid var(--border);border-radius:12px;background:var(--surface);text-align:center}
    .qr{width:180px;height:180px;margin:8px auto}
    @media print {.grid{grid-template-columns:repeat(3,1fr)} .btn,.actions,.sidebar,.top,.header{display:none}}
  </style>
  <script>
    function ensureQRCodeLib(cb){
      if (window.QRCode) { cb(); return; }
      var c=document.createElement('script');
      c.src='https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js';
      c.onload=function(){ cb(); };
      c.onerror=function(){
        var u=document.createElement('script');
        u.src='https://unpkg.com/qrcodejs@1.0.0/qrcode.min.js';
        u.onload=function(){ cb(); };
        u.onerror=function(){
          var s=document.createElement('script');
          s.src='/adminfrequencia/qrcode.min.js';
          s.onload=function(){ cb(); };
          s.onerror=function(){ console.error('Falha ao carregar biblioteca de QR'); };
          document.head.appendChild(s);
        };
        document.head.appendChild(u);
      };
      document.head.appendChild(c);
    }
  </script>
</head>
<body>
  <?php if (($theme ?? '')!=='dark'){ ?>
    <div class="header branded">
      <div class="row">
        <div class="brand-block">
          <?php $logo = $_SESSION['escola_logo'] ?? ''; $nome = $_SESSION['escola_nome'] ?? 'Escola'; $user = $_SESSION['user_name'] ?? 'Usuário'; ?>
          <img src="<?php echo $logo ?: '/adminfrequencia/avatar.svg'; ?>" alt="">
          <div>
            <div class="brand">QR Codes • <?php echo htmlspecialchars($nome); ?></div>
            <div class="user">Usuário: <?php echo htmlspecialchars($user); ?></div>
          </div>
        </div>
        <span class="badge ok" style="visibility:hidden">Conectado</span>
      </div>
      <div class="row"><a class="btn-secondary" href="?<?php echo $school_view? 'escola='.$school_view.'&':''; ?>theme=dark">Tema escuro</a></div>
    </div>
  <?php } else { ?>
    <div class="top"><div>Admin • QR Codes</div><div class="muted"><?php echo htmlspecialchars($msg); ?></div></div>
  <?php } ?>
  <div class="layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div class="content">
      <div class="row" style="align-items:center;justify-content:space-between">
        <h2 class="title" style="margin:0">Gerar QR Codes</h2>
        <div class="actions" style="display:flex;gap:8px">
          <button class="btn-secondary" type="button" onclick="generatePDF()">Imprimir (PDF)</button>
        </div>
      </div>
      <form method="get" class="row" id="formQR">
        <?php if ($eid===null){ ?>
          <select name="escola" required onchange="document.getElementById('formQR').submit()">
            <option value="">Selecione a escola</option>
            <?php foreach ($schools as $s){ ?>
              <option value="<?php echo $s['id']; ?>" <?php echo ((string)$school_view===(string)$s['id'])?'selected':''; ?>><?php echo htmlspecialchars($s['nome']); ?></option>
            <?php } ?>
          </select>
        <?php } ?>
        <select name="mode" onchange="document.getElementById('formQR').submit()">
          <option value="turma" <?php echo $mode==='turma'?'selected':''; ?>>Por Turma</option>
          <option value="aluno" <?php echo $mode==='aluno'?'selected':''; ?>>Individual</option>
        </select>
        <?php if ($mode==='turma'){ ?>
          <select name="turma" required onchange="document.getElementById('formQR').submit()">
            <option value="">Selecione a turma</option>
            <?php foreach ($turmas as $t){ ?>
              <option value="<?php echo $t['id']; ?>" <?php echo $tid===$t['id']?'selected':''; ?>><?php echo htmlspecialchars($t['serie'].' • '.$t['nome']); ?></option>
            <?php } ?>
          </select>
        <?php } else { ?>
          <select name="aluno" required onchange="document.getElementById('formQR').submit()">
            <option value="">Selecione o aluno</option>
            <?php if ($school_view){ 
              $as = $pdo->prepare('SELECT id,nome FROM alunos WHERE escola_id=? ORDER BY nome'); $as->execute([$school_view]); $opts = $as->fetchAll();
              foreach ($opts as $a){ ?>
                <option value="<?php echo $a['id']; ?>" <?php echo $aid===$a['id']?'selected':''; ?>><?php echo htmlspecialchars($a['nome']); ?></option>
              <?php } } else { ?>
              <option value="">Selecione a escola para listar alunos</option>
              <?php } ?>
          </select>
        <?php } ?>
        <button class="btn" type="submit">Gerar</button>
      </form>
      <div class="grid" id="grid">
        <?php foreach ($alunos as $a){ 
          $code = ShortCode::makeCode((int)$school_view, (int)$a['matricula'], Env::get('QR_SECRET','dev-secret'));
          try { $pdo->prepare('UPDATE alunos SET codigo_curto=? WHERE id=?')->execute([$code, (int)$a['id']]); } catch (\Throwable $e) {}
        ?>
          <div class="card">
            <div class="muted" style="font-weight:600"><?php echo htmlspecialchars($a['nome']); ?></div>
            <div class="muted"><?php echo htmlspecialchars(($a['serie'] ?? '')); ?> • <?php echo htmlspecialchars(($a['turma'] ?? '')); ?></div>
            <div class="qr" data-code="<?php echo htmlspecialchars($code); ?>"></div>
            <div class="actions" style="margin-top:8px">
              <button class="btn-secondary" type="button" onclick="downloadPNG(this)">Baixar PNG</button>
            </div>
            <div style="display:none"
                 data-escola-name="<?php echo htmlspecialchars($escola_nome); ?>"
                 data-escola-logo="<?php echo htmlspecialchars($escola_logo ?: '/adminfrequencia/avatar.svg'); ?>"
                 data-nome="<?php echo htmlspecialchars($a['nome']); ?>"
                 data-serie="<?php echo htmlspecialchars(($a['serie'] ?? '')); ?>"
                 data-turma="<?php echo htmlspecialchars(($a['turma'] ?? '')); ?>"
                 data-matricula="<?php echo htmlspecialchars((string)($a['matricula'] ?? '')); ?>"></div>
          </div>
        <?php } ?>
        <?php if (!$alunos && $mode==='turma'){ ?>
          <div class="muted">Selecione uma turma para gerar os QR Codes.</div>
        <?php } elseif (!$alunos && $mode==='aluno'){ ?>
          <div class="muted">Selecione um aluno para gerar o QR Code.</div>
        <?php } ?>
      </div>
    </div>
  </div>
  <script>
    function renderQRCodes(){
      document.querySelectorAll('.qr').forEach(function(el){
        var code=el.getAttribute('data-code');
        var q=new QRCode(el,{width:180,height:180});
        q.makeCode(code);
      });
    }
    function fitTitle(ctx,text,maxW){
      var size=34; var f='Arial'; ctx.font='bold '+size+'px '+f;
      while (ctx.measureText(text).width>maxW && size>18){ size-=1; ctx.font='bold '+size+'px '+f; }
      return size;
    }
    function ensureJsPDF(cb){
      if (window.jspdf && window.jspdf.jsPDF) { cb(); return; }
      var s=document.createElement('script');
      s.src='https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js';
      s.onload=function(){ cb(); };
      s.onerror=function(){ console.error('Falha ao carregar jsPDF'); };
      document.head.appendChild(s);
    }
    function downloadPNG(btn){
      var card=btn.closest('.card');
      var meta=card.querySelector('div[style=\"display:none\"]');
      var escola=meta.getAttribute('data-escola-name');
      var logo=meta.getAttribute('data-escola-logo');
      var nome=meta.getAttribute('data-nome');
      var serie=meta.getAttribute('data-serie');
      var turma=meta.getAttribute('data-turma');
      var matricula=meta.getAttribute('data-matricula');
      var qrCanvas=card.querySelector('canvas'); var qrImg=card.querySelector('img');
      var qrSrc=qrCanvas? qrCanvas.toDataURL('image/png') : (qrImg? qrImg.src : null);
      if(!qrSrc){ return }
      var W=800, PAD=24;
      // precompute dynamic height based on content
      var CX=PAD, CY=PAD, CW=W-2*PAD;
      var headerH=180, headerW = CW-48;
      var qrPadding=28;
      var qrSize = Math.min(headerW - qrPadding*2, 540);
      var qrCardY = CY+24+headerH+32;
      var qy = qrCardY + qrPadding;
      var bottomMargin = 45;
      var yFooter = qy + qrSize + 230 + bottomMargin;
      var H = yFooter + PAD;
      var canvas=document.createElement('canvas'); canvas.width=W; canvas.height=H; var ctx=canvas.getContext('2d');
      ctx.fillStyle='#F5F7FB'; ctx.fillRect(0,0,W,H);
      function roundRect(x,y,w,h,r){ctx.beginPath();ctx.moveTo(x+r,y);ctx.arcTo(x+w,y,x+w,y+h,r);ctx.arcTo(x+w,y+h,x,y+h,r);ctx.arcTo(x,y+h,x,y,r);ctx.arcTo(x,y,x+w,y,r);ctx.closePath();}
      // card
      var CH=H-2*PAD;
      roundRect(CX,CY,CW,CH,28); ctx.fillStyle='#FFFFFF'; ctx.fill(); ctx.strokeStyle='#E5E8EF'; ctx.lineWidth=1; ctx.stroke();
      // header
      roundRect(CX+24,CY+24,CW-48,headerH,16); ctx.strokeStyle='#E5E8EF'; ctx.stroke();
      var logoBoxW=160;
      roundRect(CX+24,CY+24,logoBoxW,headerH,16); ctx.stroke();
      var maxTitleW = (CW-48) - logoBoxW - 60;
      var titleSize = fitTitle(ctx, escola || 'Escola', maxTitleW);
      ctx.fillStyle='#1F2937'; ctx.font='bold '+titleSize+'px Arial';
      ctx.fillText(escola || 'Escola', CX+24+logoBoxW+20, CY+24+54);
      ctx.font='18px Arial'; ctx.fillStyle='#4B5563';
      ctx.fillText('Ensino Médio Profissional', CX+24+logoBoxW+20, CY+24+92);
      // accent line
      ctx.fillStyle='#93C5FD'; ctx.fillRect(CX+24+logoBoxW+20, CY+24+108, 360, 4);
      // painel abaixo do cabeçalho, alinhado à largura do cabeçalho
      var qrCardY = CY+24+headerH+32;
      // draw logo
      function loadImage(src){return new Promise(function(res){var i=new Image(); i.crossOrigin='anonymous'; i.onload=function(){res(i)}; i.onerror=function(){res(null)}; i.src=src;})}
      Promise.all([loadImage(logo), loadImage(qrSrc)]).then(function(arr){
        var logoImg=arr[0], qrImage=arr[1];
        if (logoImg){ ctx.drawImage(logoImg, CX+24+16, CY+24+16, logoBoxW-32, headerH-32); }
        // QR com mesma largura interna do cabeçalho, sem molduras extras
        var headerX = CX+24;
        var panelH = qrPadding + qrSize + 188 + bottomMargin;
        roundRect(headerX, qrCardY, headerW, panelH, 24); ctx.fillStyle='#FFFFFF'; ctx.fill(); ctx.strokeStyle='#E5E8EF'; ctx.stroke();
        var qx = headerX + (headerW - qrSize)/2;
        roundRect(qx-18, qy-18, qrSize+36, qrSize+36, 18); ctx.fillStyle='#FFFFFF'; ctx.fill(); ctx.strokeStyle='#E5E8EF'; ctx.stroke();
        if (qrImage){ ctx.drawImage(qrImage, qx, qy, qrSize, qrSize); }
        // student texts
        ctx.fillStyle='#1F2937'; ctx.font='bold 28px Arial';
        ctx.fillText(nome || '', headerX + 24, qy + qrSize + 60);
        ctx.font='26px Arial'; ctx.fillStyle='#374151';
        ctx.fillText((serie||'')+' • '+(turma||''), headerX + 24, qy + qrSize + 100);
        // divider
        ctx.fillStyle='#E5E8EF'; ctx.fillRect(headerX + 24, qy + qrSize + 118, headerW - 48, 2);
        // matricula + footer
        ctx.fillStyle='#4B5563'; ctx.font='22px Arial';
        ctx.fillText('Matrícula: '+(matricula||''), headerX + 24, qy + qrSize + 160);
        // rodapé centralizado
        ctx.font='22px Arial'; ctx.fillStyle='#6B7280'; ctx.textAlign='center';
        ctx.fillText('Sistema de Frequência Digital', headerX + headerW/2, qy + qrSize + 188);
        ctx.textAlign='left';
        var a=document.createElement('a'); a.href=canvas.toDataURL('image/png'); a.download='qrcode_'+(nome||'aluno')+'.png'; a.click();
      });
    }
    // removidos blocos duplicados de ensureJsPDF, buildCardCanvasMeta e generatePDF
    function ensureJsPDF(cb){
      if (window.jspdf && window.jspdf.jsPDF) { cb(); return; }
      var s=document.createElement('script');
      s.src='https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js';
      s.onload=function(){ cb(); };
      s.onerror=function(){ console.error('Falha ao carregar jsPDF'); };
      document.head.appendChild(s);
    }
    async function buildCardCanvas(meta){
      var escola=meta.escola, logo=meta.logo, nome=meta.nome, serie=meta.serie, turma=meta.turma, matricula=meta.matricula, qrSrc=meta.qrSrc;
      var W=680, PAD=24;
      var CX=PAD, CY=PAD, CW=W-2*PAD;
      var headerH=180, headerW = CW-48;
      var qrPadding=28;
      var qrSize = Math.min(headerW - qrPadding*2, 540);
      var qrCardY = CY+24+headerH+32;
      var qy = qrCardY + qrPadding;
      var bottomMargin = 45;
      var yFooter = qy + qrSize + 230 + bottomMargin;
      var H = yFooter + PAD;
      var canvas=document.createElement('canvas'); canvas.width=W; canvas.height=H; var ctx=canvas.getContext('2d');
      ctx.fillStyle='#F5F7FB'; ctx.fillRect(0,0,W,H);
      function roundRect(x,y,w,h,r){ctx.beginPath();ctx.moveTo(x+r,y);ctx.arcTo(x+w,y,x+w,y+h,r);ctx.arcTo(x+w,y+h,x,y+h,r);ctx.arcTo(x,y+h,x,y,r);ctx.arcTo(x,y,x+w,y,r);ctx.closePath();}
      var CH=H-2*PAD;
      roundRect(CX,CY,CW,CH,28); ctx.fillStyle='#FFFFFF'; ctx.fill(); ctx.strokeStyle='#E5E8EF'; ctx.lineWidth=1; ctx.stroke();
      roundRect(CX+24,CY+24,CW-48,headerH,16); ctx.strokeStyle='#E5E8EF'; ctx.stroke();
      var logoBoxW=160;
      roundRect(CX+24,CY+24,logoBoxW,headerH,16); ctx.stroke();
      var maxTitleW = (CW-48) - logoBoxW - 60;
      var titleSize = fitTitle(ctx, escola || 'Escola', maxTitleW);
      ctx.fillStyle='#1F2937'; ctx.font='bold '+titleSize+'px Arial';
      ctx.fillText(escola || 'Escola', CX+24+logoBoxW+20, CY+24+54);
      ctx.font='18px Arial'; ctx.fillStyle='#4B5563';
      ctx.fillText('Ensino Médio Profissional', CX+24+logoBoxW+20, CY+24+92);
      ctx.fillStyle='#93C5FD'; ctx.fillRect(CX+24+logoBoxW+20, CY+24+108, 360, 4);
      var headerX = CX+24;
      var panelH = qrPadding + qrSize + 188 + bottomMargin;
      roundRect(headerX, qrCardY, headerW, panelH, 24); ctx.fillStyle='#FFFFFF'; ctx.fill(); ctx.strokeStyle='#E5E8EF'; ctx.stroke();
      var qx = headerX + (headerW - qrSize)/2;
      roundRect(qx-18, qy-18, qrSize+36, qrSize+36, 18); ctx.fillStyle='#FFFFFF'; ctx.fill(); ctx.strokeStyle='#E5E8EF'; ctx.stroke();
      function loadImage(src){return new Promise(function(res){var i=new Image(); i.crossOrigin='anonymous'; i.onload=function(){res(i)}; i.onerror=function(){res(null)}; i.src=src;})}
      var arr = await Promise.all([loadImage(logo), loadImage(qrSrc)]);
      var logoImg=arr[0], qrImage=arr[1];
      if (logoImg){ ctx.drawImage(logoImg, CX+24+16, CY+24+16, logoBoxW-32, headerH-32); }
      if (qrImage){ ctx.drawImage(qrImage, qx, qy, qrSize, qrSize); }
      ctx.fillStyle='#1F2937'; ctx.font='bold 28px Arial';
      ctx.fillText(nome || '', headerX + 24, qy + qrSize + 60);
      ctx.font='26px Arial'; ctx.fillStyle='#374151';
      ctx.fillText((serie||'')+' • '+(turma||''), headerX + 24, qy + qrSize + 100);
      ctx.fillStyle='#E5E8EF'; ctx.fillRect(headerX + 24, qy + qrSize + 118, headerW - 48, 2);
      ctx.fillStyle='#4B5563'; ctx.font='22px Arial';
      ctx.fillText('Matrícula: '+(matricula||''), headerX + 24, qy + qrSize + 160);
      ctx.font='22px Arial'; ctx.fillStyle='#6B7280'; ctx.textAlign='center';
      ctx.fillText('Sistema de Frequência Digital', headerX + headerW/2, qy + qrSize + 188);
      ctx.textAlign='left';
      return canvas;
    }
    function generatePDF(){
      ensureJsPDF(async function(){
        const { jsPDF } = window.jspdf;
        var doc = new jsPDF('portrait','pt','a4');
        var pageW = doc.internal.pageSize.getWidth();
        var pageH = doc.internal.pageSize.getHeight();
        var margin = 10;
        var gutter = 6;
        var cols = 3, rows = 3;
        var cards = Array.from(document.querySelectorAll('.card'));
        var entries = [];
        for (var i=0;i<cards.length;i++){
          var card=cards[i];
          var metaEl=card.querySelector('div[style=\"display:none\"]');
          var qrCanvas=card.querySelector('canvas'); var qrImg=card.querySelector('img');
          var qrSrc=qrCanvas? qrCanvas.toDataURL('image/png') : (qrImg? qrImg.src : null);
          if(!qrSrc) continue;
          entries.push({
            escola:metaEl.getAttribute('data-escola-name'),
            logo:metaEl.getAttribute('data-escola-logo'),
            nome:metaEl.getAttribute('data-nome'),
            serie:metaEl.getAttribute('data-serie'),
            turma:metaEl.getAttribute('data-turma'),
            matricula:metaEl.getAttribute('data-matricula'),
            qrSrc:qrSrc
          });
        }
        if (entries.length===0){ return }
        var sample = await buildCardCanvas(entries[0]);
        var ratio = sample.height / sample.width;
        var cardW = (pageW - 2*margin - (cols-1)*gutter) / cols;
        var cardH = cardW * ratio;
        var needH = rows * cardH + (rows-1) * gutter + 2 * margin;
        var availH = pageH;
        if (needH > availH){
          var scale = (pageH - 2*margin - (rows-1)*gutter) / (rows * cardH);
          cardW *= scale; cardH *= scale;
        }
        var totalW = cols * cardW + (cols - 1) * gutter;
        var leftOffset = (pageW - totalW) / 2;
        var count = 0;
        for (var j=0;j<entries.length;j++){
          var canvas = (j===0) ? sample : await buildCardCanvas(entries[j]);
          var dataURL = canvas.toDataURL('image/jpeg', 0.7);
          var col = count % cols;
          var row = Math.floor(count / cols) % rows;
          var x = leftOffset + col * (cardW + gutter);
          var y = margin + row * (cardH + gutter);
          doc.addImage(dataURL, 'JPEG', x, y, cardW, cardH);
          count++;
          if (count % (cols*rows) === 0 && j < entries.length-1) { doc.addPage(); }
        }
        doc.save('qrcodes.pdf');
      });
    }
    // Removido blocos duplicados; usar apenas generatePDF abaixo
    ensureQRCodeLib(renderQRCodes);
  </script>
</body>
</html>
