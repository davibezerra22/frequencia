<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
use App\Database\Connection;
$pdo = Connection::get();
$msg = '';
$session_escola = $_SESSION['escola_id'] ?? null;
$ano_atual = $session_escola
  ? ($pdo->prepare("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id' AND escola_id=?")->execute([$session_escola]) ? ($pdo->query("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id' AND escola_id=".$session_escola)->fetch()['valor'] ?? null) : null)
  : ($pdo->query("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id'")->fetch()['valor'] ?? null);
$turmas = [];
if ($ano_atual) {
    $s = $pdo->prepare('SELECT t.id, t.nome, s.nome AS serie FROM turmas t JOIN series s ON s.id=t.serie_id WHERE t.ano_letivo_id=? ORDER BY s.nome, t.nome');
    $s->execute([$ano_atual]);
    $turmas = $s->fetchAll();
}
// normaliza strings para UTF-8 quando o CSV vier em ISO-8859-1/Windows-1252
$toUtf8 = function($s){
    $s = (string)$s;
    if ($s === '') return $s;
    if (function_exists('mb_check_encoding') && mb_check_encoding($s, 'UTF-8')) return $s;
    $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $s);
    if ($converted !== false && $converted !== '') return $converted;
    $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $s);
    return ($converted !== false && $converted !== '') ? $converted : $s;
};
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $f = $_FILES['csv'];
    if ($f['error'] === UPLOAD_ERR_OK) {
        $rows = [];
        $h = fopen($f['tmp_name'], 'r');
        while (($data = fgetcsv($h, 0, ';', '"', '\\')) !== false) {
            if ($data === [null] || (count($data) === 1 && trim((string)$data[0]) === '')) { continue; }
            if (isset($data[0])) { $data[0] = ltrim($data[0], "\xEF\xBB\xBF"); }
            $rows[] = $data;
        }
        fclose($h);
        $insAluno = $pdo->prepare('INSERT INTO alunos (nome, matricula, foto_aluno, qrcode_hash, escola_id) VALUES (?, ?, ?, ?, ?)');
        $selAluno = $pdo->prepare('SELECT id FROM alunos WHERE matricula=?');
        $enturmar = $pdo->prepare('INSERT IGNORE INTO matriculas_turma (aluno_id, turma_id) VALUES (?, ?)');
        $count_enturmados = 0;
        $count_novos = 0;
        $count_existentes = 0;
        $count_invalidos = 0;
        $erros = [];
        foreach ($rows as $i => $r) {
            if ($i === 0 && preg_match('/nome/i', $r[0] ?? '')) continue;
            $nome = trim($toUtf8($r[0] ?? ''));
            $matricula = trim($r[1] ?? '');
            $foto = trim($toUtf8($r[2] ?? ''));
            if ($nome === '' || $matricula === '') { $count_invalidos++; $erros[] = ['linha'=>$i+1,'nome'=>$nome,'matricula'=>$matricula,'motivo'=>'Dados ausentes']; continue; }
            if (!preg_match('/^\d+$/', $matricula)) { $count_invalidos++; $erros[] = ['linha'=>$i+1,'nome'=>$nome,'matricula'=>$matricula,'motivo'=>'Matrícula inválida']; continue; }
            $selAluno->execute([$matricula]);
            $aluno = $selAluno->fetch();
            if (!$aluno) {
                $qrcode = sha1($matricula);
                try { $insAluno->execute([$nome, $matricula, $foto ?: null, $qrcode, $session_escola]); $aluno_id = (int)$pdo->lastInsertId(); $count_novos++; } catch (\Throwable $e) { $count_invalidos++; $erros[] = ['linha'=>$i+1,'nome'=>$nome,'matricula'=>$matricula,'motivo'=>'Erro ao inserir']; continue; }
            } else { $aluno_id = (int)$aluno['id']; $count_existentes++; }
            if ($turma_id) {
                try { $enturmar->execute([$aluno_id, $turma_id]); $count_enturmados++; } catch (\Throwable $e) { $erros[] = ['linha'=>$i+1,'nome'=>$nome,'matricula'=>$matricula,'motivo'=>'Erro ao enturmar']; }
            }
        }
        $msg = 'Importação: '.$count_novos.' novos • '.$count_existentes.' existentes • '.$count_enturmados.' enturmados • '.$count_invalidos.' falhas';
        $_SESSION['import_erros'] = $erros;
    } else {
        $msg = 'Falha no upload';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin • Importar Alunos</title>
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
            <div class="brand">Importar Alunos • <?php echo htmlspecialchars($nome); ?></div>
            <div class="user">Usuário: <?php echo htmlspecialchars($user); ?></div>
          </div>
        </div>
        <span class="badge ok" style="visibility:hidden">Conectado</span>
      </div>
    </div>
      <div class="row"><a class="btn-secondary" href="?theme=dark">Tema escuro</a></div>
    </div>
  <?php } else { ?>
    <div class="top"><div>Admin • Importar Alunos</div><div class="muted"><?php echo htmlspecialchars($msg); ?></div></div>
    <div class="layout">
      <?php require __DIR__ . '/_sidebar.php'; ?>
      <div class="content"><div class="row" style="margin-top:10px"><a class="btn" href="?">Tema claro</a></div></div>
    </div>
    </body>
    </html>
    <?php return; ?>
  <?php } ?>
  <div class="layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div class="content">
      <h2 class="title">Upload CSV</h2>
      <form method="post" enctype="multipart/form-data" class="row">
        <select name="turma_id" required>
          <option value="">Selecione a turma</option>
          <?php foreach ($turmas as $t){ ?>
            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['serie'].' • '.$t['nome']); ?></option>
          <?php } ?>
        </select>
        <input type="file" name="csv" accept=".csv" required>
        <button type="submit">Importar</button>
      </form>
      <div class="muted">CSV com separador <strong>;</strong> e colunas: <code>nome;matricula;foto</code>. Cabeçalho opcional na primeira linha.</div>
      <h2 class="title">Exemplo</h2>
      <div class="sample">
        <code>nome;matricula;foto<br>Maria Silva;20250001;<br>João Souza;20250002;<br>...</code>
      </div>
      <div class="muted" style="margin-top:10px">Se o aluno já existir por matrícula, apenas será enturmado.</div>
    </div>
  </div>
  <div class="modal-backdrop" id="modalImportSummary">
    <div class="modal">
      <div class="hd"><div>Resumo da Importação</div><button class="btn-secondary" type="button" onclick="closeImportSummary()">Fechar</button></div>
      <div class="bd">
        <div class="muted" style="margin-bottom:8px"><?php echo htmlspecialchars($msg); ?></div>
        <?php $errs = $_SESSION['import_erros'] ?? []; if ($errs){ ?>
          <table>
            <thead><tr><th>Linha</th><th>Nome</th><th>Matrícula</th><th>Motivo</th></tr></thead>
            <tbody>
              <?php foreach ($errs as $e){ ?>
                <tr>
                  <td><?php echo (int)$e['linha']; ?></td>
                  <td><?php echo htmlspecialchars((string)$e['nome']); ?></td>
                  <td><?php echo htmlspecialchars((string)$e['matricula']); ?></td>
                  <td><?php echo htmlspecialchars((string)$e['motivo']); ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        <?php } else { ?>
          <div class="muted">Sem falhas registradas.</div>
        <?php } ?>
      </div>
      <div class="ft"><button class="btn-secondary" type="button" onclick="closeImportSummary()">Concluir</button></div>
    </div>
  </div>
  <script src="/adminfrequencia/modal.js"></script>
  <script>
    <?php if ($msg){ ?>
      openImportSummary();
      <?php $_SESSION['import_erros'] = []; ?>
    <?php } ?>
  </script>
</body>
</html>
