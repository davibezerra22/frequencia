<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
use App\Database\Connection;
$pdo = Connection::get();
$msg = '';
$processFoto = function(int $aluno_id) use ($pdo) {
  if (!isset($_FILES['foto_file']) || ($_FILES['foto_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return;
  $f = $_FILES['foto_file'];
  $mime = (function($path,$fallback){
    if (function_exists('finfo_open')) {
      $fi = @finfo_open(FILEINFO_MIME_TYPE);
      if ($fi) { $t = @finfo_file($fi, $path); @finfo_close($fi); if ($t) return $t; }
    }
    if (function_exists('exif_imagetype')) {
      $map = [IMAGETYPE_PNG=>'image/png',IMAGETYPE_JPEG=>'image/jpeg',IMAGETYPE_WEBP=>'image/webp'];
      $t = @exif_imagetype($path); if ($t && isset($map[$t])) return $map[$t];
    }
    return $fallback ?: 'application/octet-stream';
  })($f['tmp_name'], $f['type'] ?? '');
  $ok = in_array($mime, ['image/png','image/jpeg','image/webp'], true);
  if (!$ok) return;
  $dir = realpath(__DIR__ . '/../uploads') ?: __DIR__ . '/../uploads';
  $photosDir = $dir . '/alunos';
  if (!is_dir($photosDir)) { @mkdir($photosDir, 0775, true); }
  $name = 'aluno_'.$aluno_id.'_'.substr(sha1_file($f['tmp_name']),0,12);
  $webPath = '/uploads/alunos/'.$name.'.webp';
  $dest = $photosDir.'/'.$name.'.webp';
  $converted = false;
  if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
    $img = @imagecreatefromstring(file_get_contents($f['tmp_name']));
    if ($img) {
      $w = imagesx($img); $h = imagesy($img);
      $maxW = 256;
      if ($w > $maxW) {
        $nw = $maxW; $nh = (int)round($h * ($maxW / $w));
        $res = imagecreatetruecolor($nw, $nh);
        imagealphablending($res, false); imagesavealpha($res, true);
        imagecopyresampled($res, $img, 0,0,0,0, $nw,$nh, $w,$h);
        $img = $res;
      }
      if (@imagewebp($img, $dest, 80)) { $converted = true; }
      imagedestroy($img);
    }
  }
  if (!$converted) {
    $ext = $mime === 'image/png' ? '.png' : ($mime === 'image/jpeg' ? '.jpg' : '.webp');
    $webPath = '/uploads/alunos/'.$name.$ext;
    $dest = $photosDir.'/'.$name.$ext;
    @move_uploaded_file($f['tmp_name'], $dest);
  }
  $upd = $pdo->prepare('UPDATE alunos SET foto_aluno=? WHERE id=?');
  try { $upd->execute([$webPath, $aluno_id]); } catch (\Throwable $e) {}
  // Gerar encoding facial automaticamente
  try {
  $api = 'http://127.0.0.1:8787/gerar-encoding';
    $b64 = null;
    if (is_file($dest)) {
      $data = @file_get_contents($dest);
      if ($data!==false) { $b64 = base64_encode($data); }
    }
    $payload = json_encode($b64 ? ['image_base64' => $b64, 'student_id' => $aluno_id] : ['image_url' => $webPath, 'student_id' => $aluno_id], JSON_UNESCAPED_SLASHES);
  $resp = null; $code = null;
  if (function_exists('curl_init')) {
    $ch = curl_init($api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
  } else {
    $context = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $payload,
        'timeout' => 5
      ]
    ]);
    $resp = @file_get_contents($api, false, $context);
    $code = null;
    if (isset($http_response_header[0])) {
      if (preg_match('#HTTP/\\S+\\s+(\\d+)#',$http_response_header[0],$m)) { $code = (int)$m[1]; }
    }
  }
  try {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    @file_put_contents($logDir.'/encode.log', date('c')." aluno={$aluno_id} http={$code} resp=".substr((string)$resp,0,600)."\n", FILE_APPEND);
  } catch (\Throwable $e) {}
  if ($resp && $code===200) {
    $obj = json_decode($resp, true);
    if (is_array($obj) && ($obj['status'] ?? '')==='ok' && isset($obj['encoding']) && is_array($obj['encoding'])) {
      $encJson = json_encode($obj['encoding']);
      try { $pdo->prepare('UPDATE alunos SET face_encoding=? WHERE id=?')->execute([$encJson, $aluno_id]); } catch (\Throwable $e) {}
      }
    }
  } catch (\Throwable $e) {}
};
$session_escola = $_SESSION['escola_id'] ?? null;
$view_escola = $session_escola ?: (int)($_GET['escola'] ?? 0) ?: null;
$schools = [];
if ($session_escola === null) { $schools = $pdo->query('SELECT id, nome FROM escolas ORDER BY nome')->fetchAll(); }
$series = $pdo->query('SELECT id, nome FROM series ORDER BY nome')->fetchAll();
$anos_stmt = $view_escola
  ? $pdo->prepare('SELECT id, ano FROM anos_letivos WHERE escola_id=? ORDER BY ano DESC')
  : $pdo->prepare('SELECT id, ano FROM anos_letivos ORDER BY ano DESC');
if ($view_escola) { $anos_stmt->execute([$view_escola]); } else { $anos_stmt->execute(); }
$anos = $anos_stmt->fetchAll();
$ano_atual = $view_escola
  ? ($pdo->prepare("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id' AND escola_id=?")->execute([$view_escola]) ? ($pdo->query("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id' AND escola_id=".$view_escola)->fetch()['valor'] ?? null) : null)
  : ($pdo->query("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id'")->fetch()['valor'] ?? null);
$turmas = [];
if ($ano_atual) {
  $s = $pdo->prepare('SELECT t.id, t.nome, s.nome AS serie FROM turmas t JOIN series s ON s.id=t.serie_id WHERE t.ano_letivo_id=? ORDER BY s.nome, t.nome');
  $s->execute([$ano_atual]);
  $turmas = $s->fetchAll();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['act'] ?? '';
  if ($act === 'create_aluno') {
    $nome = trim($_POST['nome'] ?? '');
    $matricula = trim($_POST['matricula'] ?? '');
    $foto = trim($_POST['foto'] ?? '');
    if ($nome !== '' && $matricula !== '') {
      $qrcode = sha1($matricula);
      $s = $pdo->prepare('INSERT INTO alunos (nome, matricula, foto_aluno, qrcode_hash, escola_id) VALUES (?, ?, ?, ?, ?)');
      try { 
        $s->execute([$nome, $matricula, $foto ?: null, $qrcode, $session_escola]); 
        $aluno_id = (int)$pdo->lastInsertId();
        $processFoto($aluno_id);
        $msg = 'Aluno cadastrado'; 
      } catch (\Throwable $e) { $msg = 'Erro ao cadastrar aluno'; }
    } else { $msg = 'Preencha nome e matrícula'; }
  } elseif ($act === 'update_aluno') {
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $foto = trim($_POST['foto'] ?? '');
    $s = $pdo->prepare('UPDATE alunos SET nome=?, foto_aluno=? WHERE id=?');
    try { 
      $s->execute([$nome, $foto ?: null, $id]); 
      $processFoto($id);
      $msg = 'Aluno atualizado'; 
    } catch (\Throwable $e) { $msg = 'Erro ao atualizar aluno'; }
  } elseif ($act === 'delete_aluno') {
    $id = (int)($_POST['id'] ?? 0);
    try {
      $pdo->prepare('DELETE FROM matriculas_turma WHERE aluno_id=?')->execute([$id]);
      $pdo->prepare('DELETE FROM alunos WHERE id=?')->execute([$id]);
      $msg = 'Aluno excluído';
    } catch (\Throwable $e) { $msg = 'Erro ao excluir aluno'; }
  } elseif ($act === 'enturmar') {
    $aluno_id = (int)($_POST['aluno_id'] ?? 0);
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $s = $pdo->prepare('INSERT IGNORE INTO matriculas_turma (aluno_id, turma_id) VALUES (?, ?)');
    try { $s->execute([$aluno_id, $turma_id]); $msg = 'Aluno enturmado'; } catch (\Throwable $e) { $msg = 'Erro ao enturmar'; }
  } elseif ($act === 'upload_foto') {
    $id = (int)($_POST['id'] ?? 0);
    $processFoto($id);
    $msg = 'Foto atualizada';
  }
}
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 20;
$offset = ($page - 1) * $per;
$f_turma = (int)($_GET['turma'] ?? 0);
$f_serie = (int)($_GET['serie'] ?? 0);
$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($f_turma) { $where .= ' AND mt.turma_id=?'; $params[] = $f_turma; }
if ($f_serie) { $where .= ' AND s.id=?'; $params[] = $f_serie; }
if ($q !== '') { $where .= ' AND (a.nome LIKE ? OR a.matricula LIKE ?)'; $like = '%'.$q.'%'; $params[] = $like; $params[] = $like; }
$sqlCount = 'SELECT COUNT(DISTINCT a.id) AS c
FROM alunos a
LEFT JOIN matriculas_turma mt ON mt.aluno_id=a.id
LEFT JOIN turmas t ON t.id=mt.turma_id
LEFT JOIN series s ON s.id=t.serie_id
WHERE 1=1'.($view_escola?' AND (a.escola_id = '.(int)$view_escola.' OR t.escola_id = '.(int)$view_escola.')':'').$where;
$stmtCount = $pdo->prepare($sqlCount); $stmtCount->execute($params);
$total = (int)($stmtCount->fetch()['c'] ?? 0);
$sql = 'SELECT a.id,a.nome,a.matricula,a.foto_aluno,
GROUP_CONCAT(CONCAT(s.nome," • ",t.nome) SEPARATOR "; ") AS turmas
FROM alunos a
LEFT JOIN matriculas_turma mt ON mt.aluno_id=a.id
LEFT JOIN turmas t ON t.id=mt.turma_id
LEFT JOIN series s ON s.id=t.serie_id
WHERE 1=1'.($view_escola?' AND (a.escola_id = '.(int)$view_escola.' OR t.escola_id = '.(int)$view_escola.')':'').$where.'
GROUP BY a.id
ORDER BY a.nome
LIMIT '.$per.' OFFSET '.$offset;
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$alunos = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin • Alunos</title>
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
    <div class="top"><div>Admin • Alunos</div><div class="muted"><?php echo htmlspecialchars($msg); ?></div></div>
    <div class="layout">
      <?php require __DIR__ . '/_sidebar.php'; ?>
      <div class="content"><div class="row" style="margin:10px 0"><a class="btn" href="?">Tema claro</a></div></div>
    </div>
    </body>
    </html>
    <?php return; ?>
  <?php } ?>
  <div class="layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div class="content">
      <div class="row" style="align-items:center;justify-content:space-between">
        <h2 class="title" style="margin:0">Alunos</h2>
        <div style="display:flex;gap:8px;align-items:center">
          <?php if ($session_escola===null){ ?>
            <form method="get" class="row" style="gap:8px">
              <select name="escola">
                <option value="">Todas</option>
                <?php foreach ($schools as $sc){ ?>
                  <option value="<?php echo $sc['id']; ?>" <?php echo ((string)$view_escola===(string)$sc['id'])?'selected':''; ?>><?php echo htmlspecialchars($sc['nome']); ?></option>
                <?php } ?>
              </select>
              <button type="submit">Filtrar</button>
            </form>
          <?php } ?>
          <button class="btn" type="button" onclick="openAlunos()">Gerenciar Alunos</button>
        </div>
      </div>
      <form method="get" class="row">
        <select name="serie">
          <option value="">Filtrar por Série</option>
          <?php foreach ($series as $s){ ?>
            <option value="<?php echo $s['id']; ?>" <?php echo $f_serie===$s['id']?'selected':''; ?>><?php echo htmlspecialchars($s['nome']); ?></option>
          <?php } ?>
        </select>
        <select name="turma">
          <option value="">Filtrar por Turma (Ano atual)</option>
          <?php foreach ($turmas as $t){ ?>
            <option value="<?php echo $t['id']; ?>" <?php echo $f_turma===$t['id']?'selected':''; ?>><?php echo htmlspecialchars($t['serie'].' • '.$t['nome']); ?></option>
          <?php } ?>
        </select>
        <input name="q" placeholder="Buscar por nome ou matrícula" value="<?php echo htmlspecialchars($q); ?>" style="min-width:220px">
        <button type="submit">Filtrar</button>
      </form>
      <table class="zebra">
        <thead><tr><th>Nº</th><th>Aluno</th><th>Matrícula</th><th>Turmas</th><th>Ações</th></tr></thead>
        <tbody>
          <?php $i=0; foreach ($alunos as $a){ $i++; $num=$offset+$i; ?>
            <tr>
              <td><?php echo $num; ?></td>
              <td>
                <div class="row" style="gap:8px;align-items:center">
                  <?php $photo = (string)$a['foto_aluno']; if ($photo===''){ $photo='/adminfrequencia/avatar.svg'; } ?>
                  <img src="<?php echo htmlspecialchars($photo); ?>" alt="" class="avatar" onerror="this.src='/adminfrequencia/avatar.svg'">
                  <span><?php echo htmlspecialchars($a['nome']); ?></span>
                </div>
              </td>
              <td><?php echo htmlspecialchars($a['matricula']); ?></td>
              <td><?php echo htmlspecialchars((string)$a['turmas']); ?></td>
              <td style="white-space:nowrap">
                <button class="btn-secondary" type="button" onclick="openEditAluno('<?php echo $a['id']; ?>','<?php echo htmlspecialchars($a['nome']); ?>')">Editar</button>
                <button class="btn-secondary" type="button" onclick="openFotoAluno('<?php echo $a['id']; ?>')">Foto</button>
                <button class="btn-secondary" type="button" onclick="openEnturmarAluno('<?php echo $a['id']; ?>')">Enturmar</button>
                <button class="btn-secondary" type="button" onclick="openDeleteAluno('<?php echo $a['id']; ?>','<?php echo htmlspecialchars($a['nome']); ?>')">Excluir</button>
                <a class="btn-secondary" href="/adminfrequencia/qrcodes.php?mode=aluno&aluno=<?php echo $a['id']; ?>&pdf=1" style="text-decoration:none">Gerar QR</a>
              </td>
            </tr>
          <?php } ?>
          <?php if (!$alunos){ ?>
            <tr><td colspan="5" class="muted">Nenhum aluno encontrado.</td></tr>
          <?php } ?>
        </tbody>
      </table>
      <div class="row">
        <?php $pages = max(1, (int)ceil($total/$per)); $prev = max(1,$page-1); $next = min($pages,$page+1); ?>
        <?php $qs_t = $f_turma? '&turma='.$f_turma:''; $qs_s = $f_serie? '&serie='.$f_serie:''; $qs_q = $q!==''? '&q='.urlencode($q):''; ?>
        <a class="btn-secondary" href="?p=<?php echo $prev; ?><?php echo $qs_t; ?><?php echo $qs_s; ?><?php echo $qs_q; ?>" style="text-decoration:none">Anterior</a>
        <div class="muted">Página <?php echo $page; ?> de <?php echo $pages; ?></div>
        <a class="btn-secondary" href="?p=<?php echo $next; ?><?php echo $qs_t; ?><?php echo $qs_s; ?><?php echo $qs_q; ?>" style="text-decoration:none">Próxima</a>
      </div>
    </div>
  </div>
  <div class="modal-backdrop" id="modalAlunos">
    <div class="modal">
      <div class="hd"><div>Cadastrar Aluno</div><button class="btn-secondary" type="button" onclick="closeAlunos()">Fechar</button></div>
      <div class="bd">
        <div id="tab-cad">
          <form method="post" enctype="multipart/form-data" class="form-grid">
            <div class="field">
              <label>Nome</label>
              <input name="nome" required>
            </div>
            <div class="field">
              <label>Matrícula</label>
              <input name="matricula" required>
            </div>
            <div class="field">
              <label>Foto (URL)</label>
              <input name="foto" placeholder="URL da Foto (opcional)">
            </div>
            <div class="field">
              <label>Upload Foto</label>
              <input type="file" name="foto_file" accept="image/*" onchange="previewAvatar(this,'cad-avatar')">
            </div>
            <div class="span-2">
              <img id="cad-avatar" class="avatar" alt="" style="display:none">
            </div>
            <div class="span-2 actions">
              <button class="btn" name="act" value="create_aluno">Cadastrar</button>
            </div>
          </form>
        </div>
      </div>
      <div class="ft"></div>
    </div>
  </div>
  <div class="modal-backdrop" id="modalEditAluno">
    <div class="modal">
      <div class="hd"><div>Editar Aluno</div><button class="btn-secondary" type="button" onclick="closeEditAluno()">Fechar</button></div>
      <div class="bd">
        <form method="post" class="form-grid">
          <input type="hidden" name="id">
          <div class="field"><label>Nome</label><input name="nome" required></div>
          <div class="actions"><button class="btn" name="act" value="update_aluno">Salvar</button></div>
        </form>
      </div>
      <div class="ft"></div>
    </div>
  </div>
  <div class="modal-backdrop" id="modalEnturmarAluno">
    <div class="modal">
      <div class="hd"><div>Enturmar Aluno</div><button class="btn-secondary" type="button" onclick="closeEnturmarAluno()">Fechar</button></div>
      <div class="bd">
        <form method="post" class="row">
          <input type="hidden" name="aluno_id">
          <select name="turma_id" required>
            <option value="">Selecione a Turma</option>
            <?php foreach ($turmas as $t){ ?>
              <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['serie'].' • '.$t['nome']); ?></option>
            <?php } ?>
          </select>
          <button class="btn" name="act" value="enturmar">Enturmar</button>
        </form>
      </div>
      <div class="ft"></div>
    </div>
  </div>
  <div class="modal-backdrop" id="modalFotoAluno">
    <div class="modal">
      <div class="hd"><div>Upload de Foto</div><button class="btn-secondary" type="button" onclick="closeFotoAluno()">Fechar</button></div>
      <div class="bd">
        <form method="post" enctype="multipart/form-data" class="row">
          <input type="hidden" name="id">
          <input type="file" name="foto_file" accept="image/*" required>
          <button class="btn" name="act" value="upload_foto">Enviar</button>
        </form>
      </div>
      <div class="ft"></div>
    </div>
  </div>
  <div class="modal-backdrop" id="modalDeleteAluno">
    <div class="modal">
      <div class="hd"><div>Excluir Aluno</div><button class="btn-secondary" type="button" onclick="closeDeleteAluno()">Fechar</button></div>
      <div class="bd">
        <div class="muted" id="del_nome"></div>
        <form method="post" class="row">
          <input type="hidden" name="id">
          <button class="btn" name="act" value="delete_aluno">Confirmar Exclusão</button>
        </form>
      </div>
      <div class="ft"></div>
    </div>
  </div>
  <script src="/adminfrequencia/modal.js"></script>
  <script>
    <?php if ($msg){ $type = stripos($msg,'Erro')!==false ? 'err' : 'ok'; ?>
      showToast('<?php echo htmlspecialchars($msg); ?>',{type:'<?php echo $type; ?>',center:true,duration:6000})
    <?php } ?>
  </script>
  </body>
  </html>
