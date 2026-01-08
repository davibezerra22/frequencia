<?php
require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Database/Connection.php';
require_once __DIR__ . '/../../../src/Support/Env.php';
require_once __DIR__ . '/../../../src/Support/Session.php';
require_once __DIR__ . '/../../../src/Bootstrap.php';
use App\Database\Connection;
use App\Support\Env;
$pdo = Connection::get();
header('Content-Type: application/json; charset=utf-8');
App\Support\Session::start();
$escolaSess = isset($_SESSION['escola_id']) ? (int)$_SESSION['escola_id'] : null;
if (!$escolaSess) { http_response_code(401); echo json_encode(['status'=>'erro','mensagem'=>'Não autenticado']); exit; }
$input = json_decode(file_get_contents('php://input'), true);
$qr = (string)($input['qr'] ?? '');
$device = (string)($input['device_id'] ?? '');
if ($qr === '') { http_response_code(400); echo json_encode(['status'=>'erro','mensagem'=>'QR vazio']); exit; }
try {
  // Aceita código curto (7 chars) ou o formato antigo 'QRS1-...'
  $aluno = null;
  $escola_id = null;
  if (strpos($qr, 'QRS1-') === 0) {
    $parts = explode('-', $qr);
    $E = $parts[1] ?? '';
    $C = $parts[2] ?? '';
    $K = $parts[3] ?? '';
    $secret = Env::get('QR_SECRET','dev-secret');
    $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $toDec = function(string $b32) use ($alphabet): int {
      $n=0; for($i=0;$i<strlen($b32);$i++){ $n = $n*32 + strpos($alphabet, $b32[$i]); } return $n;
    };
    $hmacShort = function(string $secret, string $data, int $len=4) use ($alphabet): string {
      $h = strtoupper(hash_hmac('sha256', $data, $secret));
      $out=''; for($i=0;$i<$len;$i++){ $out .= $alphabet[ hexdec(substr($h,$i*2,2)) % 32 ]; }
      return $out;
    };
    $calcK = $hmacShort($secret, $E.'|'.$C, 4);
    if ($calcK !== $K) { http_response_code(400); echo json_encode(['status'=>'erro','mensagem'=>'Assinatura inválida']); exit; }
    $escola_id = $toDec($E);
    if ($escola_id !== $escolaSess) { http_response_code(403); echo json_encode(['status'=>'erro','mensagem'=>'Escola inválida']); exit; }
    $stmt = $pdo->prepare('SELECT a.id,a.nome,a.foto_aluno,a.escola_id FROM alunos a WHERE a.escola_id=? AND a.codigo_curto=?');
    $stmt->execute([$escola_id, $qr]);
    $aluno = $stmt->fetch();
  } else {
    $stmt = $pdo->prepare('SELECT a.id,a.nome,a.foto_aluno,a.escola_id FROM alunos a WHERE a.escola_id=? AND a.codigo_curto=?');
    $stmt->execute([$escolaSess, $qr]);
    $aluno = $stmt->fetch();
    $escola_id = $aluno['escola_id'] ?? null;
  }
  if (!$aluno) { http_response_code(404); echo json_encode(['status'=>'erro','mensagem'=>'Aluno não encontrado']); exit; }
  // obter turma atual
  $ano_atual = $escola_id ? ($pdo->query("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id' AND escola_id=".$escola_id)->fetch()['valor'] ?? null) : null;
  $turma_id = null; $serie_id = null;
  if ($ano_atual) {
    $tq = $pdo->prepare('SELECT t.id AS turma_id, s.id AS serie_id FROM matriculas_turma mt JOIN turmas t ON t.id=mt.turma_id JOIN series s ON s.id=t.serie_id WHERE mt.aluno_id=? AND t.ano_letivo_id=? LIMIT 1');
    $tq->execute([$aluno['id'], $ano_atual]);
    $row = $tq->fetch();
    if ($row) { $turma_id = (int)$row['turma_id']; $serie_id = (int)$row['serie_id']; }
  }
  if (!$turma_id) { $status='fora_contexto'; } else {
    $dup = $pdo->prepare('SELECT COUNT(*) AS c FROM frequencias WHERE aluno_id=? AND TIMESTAMP(data,hora) > (NOW() - INTERVAL 30 SECOND)');
    $dup->execute([$aluno['id']]); $status = ((int)($dup->fetch()['c'] ?? 0) > 0) ? 'duplicada' : 'ok';
  }
  if ($status==='ok' && $turma_id) {
    $horaNow = (int)date('G'); // 0-23
    $turno = ($horaNow<12)? 'manha' : (($horaNow<18)? 'tarde' : 'noite');
    $dbStatus = 'presente';
    $dupDia = $pdo->prepare('SELECT COUNT(*) AS c FROM frequencias WHERE aluno_id=? AND turma_id=? AND data=CURDATE()');
    $dupDia->execute([$aluno['id'], $turma_id]);
    if (((int)($dupDia->fetch()['c'] ?? 0)) > 0) {
      $status = 'duplicada';
    } else {
      try {
        $ins = $pdo->prepare('INSERT INTO frequencias (aluno_id, turma_id, data, hora, turno, status, justificativa_texto, usuario_registro_id, escola_id) VALUES (?, ?, CURDATE(), CURTIME(), ?, ?, NULL, ?, ?)');
        $ins->execute([$aluno['id'], $turma_id, $turno, $dbStatus, $_SESSION['user_id'] ?? null, $escola_id]);
      } catch (\PDOException $pe) {
        $status = (strpos($pe->getMessage(), 'Duplicate entry') !== false) ? 'duplicada' : 'erro';
        if ($status==='erro') { throw $pe; }
      }
    }
  }
  $serie_nome = null; $turma_nome = null;
  if ($turma_id) {
    $nt = $pdo->prepare('SELECT t.nome AS turma_nome, s.nome AS serie_nome FROM turmas t JOIN series s ON s.id=t.serie_id WHERE t.id=?');
    $nt->execute([$turma_id]); $nrow = $nt->fetch();
    if ($nrow) { $turma_nome = (string)$nrow['turma_nome']; $serie_nome = (string)$nrow['serie_nome']; }
  }
  $msg = ($status==='ok') ? 'Registro de frequência'
       : (($status==='duplicada') ? 'Frequencia já registrada'
       : (($status==='fora_contexto') ? 'Fora de contexto' : 'Falha interna'));
  echo json_encode([
    'status'=>$status,
    'aluno'=>['id'=>$aluno['id'],'nome'=>$aluno['nome'],'foto'=>$aluno['foto_aluno']],
    'serie'=>$serie_nome,
    'turma'=>$turma_nome,
    'mensagem'=>$msg
  ]);
} catch (\Throwable $e) {
  http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]);
}
