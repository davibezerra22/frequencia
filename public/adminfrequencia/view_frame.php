<?php
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Support/Session.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
App\Support\Session::start();
if (!isset($_SESSION['user_id'])) { header('Location: /adminfrequencia/login.php'); exit; }
require_once __DIR__ . '/../../src/Database/Connection.php';
use App\Database\Connection;
$pdo = Connection::get();
$escolaSess = isset($_SESSION['escola_id']) ? (int)$_SESSION['escola_id'] : null;
if (!$escolaSess) { http_response_code(401); echo 'Não autenticado'; exit; }
$fid = isset($_GET['fid']) ? (int)$_GET['fid'] : 0;
$fotoId = null;
$path = null;
$aluno = null;
$comp = null;
if ($fid>0){
  $qf = $pdo->prepare("SELECT id, path FROM frequencias_fotos WHERE escola_id=? AND frequencia_id=? ORDER BY created_at DESC LIMIT 1");
  $qf->execute([$escolaSess, $fid]);
  $fr = $qf->fetch();
  if ($fr){ $fotoId = (int)$fr['id']; $path = $fr['path']; }
  $qa = $pdo->prepare("SELECT f.aluno_id, a.nome, a.foto_aluno, f.data, f.hora, f.status, f.turno FROM frequencias f JOIN alunos a ON a.id=f.aluno_id WHERE f.id=? AND f.escola_id=?");
  $qa->execute([$fid, $escolaSess]);
  $aluno = $qa->fetch();
  $qc = $pdo->prepare("SELECT compatibilidade FROM frequencias WHERE id=? AND escola_id=?");
  try { $qc->execute([$fid, $escolaSess]); $cr = $qc->fetch(); $comp = $cr ? $cr['compatibilidade'] : null; } catch (\Throwable $e) { $comp = null; }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Visualização de Foto da Leitura</title>
  <link rel="stylesheet" href="/adminfrequencia/admin.css">
  <link rel="stylesheet" href="/adminfrequencia/light.css">
  <style>
    .layout{max-width:1100px;margin:0 auto;padding:16px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .panel{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px}
    .img{width:100%;max-height:440px;object-fit:contain;border-radius:12px;background:#EEF2F7}
    .meta{color:var(--muted);font-size:14px}
  </style>
</head>
<body>
  <div class="layout">
    <div class="panel">
      <h2>Foto do Momento da Leitura</h2>
      <div class="grid">
        <div>
          <div class="meta">Frame capturado</div>
          <?php if ($fotoId): ?>
            <img class="img" src="/adminfrequencia/frame_image.php?id=<?php echo $fotoId; ?>" alt="Frame da leitura">
          <?php else: ?>
            <div class="meta">Foto não encontrada para esta leitura</div>
          <?php endif; ?>
        </div>
        <div>
          <div class="meta">Foto oficial do aluno</div>
          <?php if ($aluno && !empty($aluno['foto_aluno'])): ?>
            <img class="img" src="<?php echo htmlspecialchars($aluno['foto_aluno'], ENT_QUOTES); ?>" alt="Foto oficial">
          <?php else: ?>
            <img class="img" src="/adminfrequencia/avatar.svg" alt="Sem foto">
          <?php endif; ?>
        </div>
      </div>
      <div class="meta" style="margin-top:12px">
        <?php if ($aluno): ?>
          Aluno: <?php echo htmlspecialchars($aluno['nome'], ENT_QUOTES); ?> • Status: <?php echo htmlspecialchars($aluno['status'] ?? '', ENT_QUOTES); ?> •
          Data/Hora: <?php echo htmlspecialchars(($aluno['data'] ?? '').' '.$aluno['hora'] ?? '', ENT_QUOTES); ?> •
          Compatibilidade: <?php echo ($comp !== null) ? ((int)$comp.'%') : 'N/D'; ?>
        <?php else: ?>
          Leitura não encontrada.
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
