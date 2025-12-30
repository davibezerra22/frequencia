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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $f = $_FILES['csv'];
    if ($f['error'] === UPLOAD_ERR_OK) {
        $rows = [];
        $h = fopen($f['tmp_name'], 'r');
        while (($data = fgetcsv($h, 0, ';')) !== false) { $rows[] = $data; }
        fclose($h);
        $insAluno = $pdo->prepare('INSERT INTO alunos (nome, matricula, foto_aluno, qrcode_hash, escola_id) VALUES (?, ?, ?, ?, ?)');
        $selAluno = $pdo->prepare('SELECT id FROM alunos WHERE matricula=?');
        $enturmar = $pdo->prepare('INSERT IGNORE INTO matriculas_turma (aluno_id, turma_id) VALUES (?, ?)');
        $count = 0;
        foreach ($rows as $i => $r) {
            if ($i === 0 && preg_match('/nome/i', $r[0] ?? '')) continue;
            $nome = trim($r[0] ?? '');
            $matricula = trim($r[1] ?? '');
            $foto = trim($r[2] ?? '');
            if ($nome === '' || $matricula === '') continue;
            $selAluno->execute([$matricula]);
            $aluno = $selAluno->fetch();
            if (!$aluno) {
                $qrcode = sha1($matricula);
                try { $insAluno->execute([$nome, $matricula, $foto, $qrcode, $session_escola]); $aluno_id = (int)$pdo->lastInsertId(); } catch (\Throwable $e) { continue; }
            } else { $aluno_id = (int)$aluno['id']; }
            if ($turma_id) { try { $enturmar->execute([$aluno_id, $turma_id]); $count++; } catch (\Throwable $e) {} }
        }
        $msg = 'Importação concluída: '.$count.' alunos enturmados';
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
</head>
<body>
  <div class="top"><div>Admin • Importar Alunos</div><div class="muted"><?php echo htmlspecialchars($msg); ?></div></div>
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
</body>
</html>
