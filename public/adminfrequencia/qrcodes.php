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
if ($mode==='turma' && $tid>0) {
  $q = $pdo->prepare('SELECT a.id,a.nome,s.nome AS serie,t.nome AS turma FROM matriculas_turma mt JOIN alunos a ON a.id=mt.aluno_id JOIN turmas t ON t.id=mt.turma_id JOIN series s ON s.id=t.serie_id WHERE mt.turma_id=? ORDER BY a.nome');
  $q->execute([$tid]); $alunos = $q->fetchAll();
} elseif ($mode==='aluno' && $aid>0) {
  $ano_stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id' AND escola_id=?"); 
  if ($school_view){ $ano_stmt->execute([$school_view]); $ano_atual = (int)($ano_stmt->fetch()['valor'] ?? 0); } else { $ano_atual = 0; }
  $q = $pdo->prepare('SELECT a.id,a.nome,s.nome AS serie,t.nome AS turma FROM alunos a LEFT JOIN matriculas_turma mt ON mt.aluno_id=a.id LEFT JOIN turmas t ON t.id=mt.turma_id LEFT JOIN series s ON s.id=t.serie_id WHERE a.id=?'.($ano_atual? ' AND t.ano_letivo_id='.$ano_atual:'').' LIMIT 1');
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
      var s=document.createElement('script');
      s.src='https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js';
      s.onload=function(){ cb(); };
      s.onerror=function(){ console.error('Falha ao carregar biblioteca de QR'); };
      document.head.appendChild(s);
    }
  </script>
</head>
<body>
  <?php if (($theme ?? '')!=='dark'){ ?>
    <div class="header branded">
      <div class="row">
        <div class="brand-block">
          <?php $logo = $_SESSION['escola_logo'] ?? ''; $nome = $_SESSION['escola_nome'] ?? 'Escola'; $user = $_SESSION['user_name'] ?? 'Usuário'; ?>
          <img src="<?php echo $logo ?: 'https://via.placeholder.com/56x56.png?text=E'; ?>" alt="">
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
          <button class="btn-secondary" type="button" onclick="window.print()">Imprimir (PDF)</button>
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
          $code = ShortCode::makeCode((int)$school_view, (int)$a['id'], Env::get('QR_SECRET','dev-secret'));
        ?>
          <div class="card">
            <div class="muted" style="font-weight:600"><?php echo htmlspecialchars($a['nome']); ?></div>
            <div class="muted"><?php echo htmlspecialchars(($a['serie'] ?? '')); ?> • <?php echo htmlspecialchars(($a['turma'] ?? '')); ?></div>
            <div class="qr" data-code="<?php echo htmlspecialchars($code); ?>"></div>
            <div class="actions" style="margin-top:8px">
              <button class="btn-secondary" type="button" onclick="downloadPNG(this)">Baixar PNG</button>
            </div>
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
    function downloadPNG(btn){
      var card=btn.closest('.card'); 
      var canvas=card.querySelector('canvas'); 
      var img=card.querySelector('img');
      var url = canvas ? canvas.toDataURL('image/png') : (img ? img.src : null);
      if(!url){ return }
      var a=document.createElement('a'); a.href=url; a.download='qrcode.png'; a.click();
    }
    ensureQRCodeLib(renderQRCodes);
    var MODE = '<?php echo $mode; ?>';
    if (MODE==='aluno'){
      setTimeout(function(){ window.print() }, 300);
    }
  </script>
</body>
</html>
