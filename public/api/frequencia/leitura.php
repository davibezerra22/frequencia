<?php
require_once __DIR__ . '/../../../src/Config/Database.php';
require_once __DIR__ . '/../../../src/Database/Connection.php';
require_once __DIR__ . '/../../../src/Bootstrap.php';
use App\Database\Connection;
use App\Support\Env;
$pdo = Connection::get();
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$qr = (string)($input['qr'] ?? '');
$device = (string)($input['device_id'] ?? '');
if ($qr === '') { http_response_code(400); echo json_encode(['status'=>'erro','mensagem'=>'QR vazio']); exit; }
if (strpos($qr, 'QRS1-') !== 0) { http_response_code(400); echo json_encode(['status'=>'erro','mensagem'=>'Formato inválido']); exit; }
try {
  $parts = explode('-', $qr);
  $E = $parts[1] ?? '';
  $C = $parts[2] ?? '';
  $K = $parts[3] ?? '';
  $secret = Env::get('QR_SECRET','dev-secret');
  // naive recheck: recompute K
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
  $aluno_id_est = $toDec(substr($C,0,strlen($C)-1));
  // confirmar aluno pela combinação escola+codigo_curto
  $stmt = $pdo->prepare('SELECT a.id,a.nome,a.foto_aluno FROM alunos a WHERE a.escola_id=? AND a.codigo_curto=?');
  $stmt->execute([$escola_id, $qr]);
  $aluno = $stmt->fetch();
  if (!$aluno) { http_response_code(404); echo json_encode(['status'=>'erro','mensagem'=>'Aluno não encontrado']); exit; }
  // obter turma atual
  $ano_atual = $pdo->query("SELECT valor FROM configuracoes WHERE chave='ano_letivo_atual_id' AND escola_id=".$escola_id)->fetch()['valor'] ?? null;
  $turma_id = null; $serie_id = null;
  if ($ano_atual) {
    $tq = $pdo->prepare('SELECT t.id AS turma_id, s.id AS serie_id FROM matriculas_turma mt JOIN turmas t ON t.id=mt.turma_id JOIN series s ON s.id=t.serie_id WHERE mt.aluno_id=? AND t.ano_letivo_id=? LIMIT 1');
    $tq->execute([$aluno['id'], $ano_atual]);
    $row = $tq->fetch();
    if ($row) { $turma_id = (int)$row['turma_id']; $serie_id = (int)$row['serie_id']; }
  }
  if (!$turma_id) { $status='fora_contexto'; } else {
    // bloquear duplicadas em 30s
    $dup = $pdo->prepare('SELECT COUNT(*) AS c FROM frequencias WHERE aluno_id=? AND leitura_at > (NOW() - INTERVAL 30 SECOND)');
    $dup->execute([$aluno['id']]); $status = ((int)($dup->fetch()['c'] ?? 0) > 0) ? 'duplicada' : 'ok';
  }
  $ins = $pdo->prepare('INSERT INTO frequencias (aluno_id, turma_id, serie_id, escola_id, leitura_at, origem, device_id, status) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)');
  $ins->execute([$aluno['id'], $turma_id, $serie_id, $escola_id, 'totem', $device ?: null, $status]);
  echo json_encode(['status'=>$status,'aluno'=>['id'=>$aluno['id'],'nome'=>$aluno['nome'],'foto'=>$aluno['foto_aluno']],'mensagem'=>'Registro de frequência']);
} catch (\Throwable $e) {
  http_response_code(500); echo json_encode(['status'=>'erro','mensagem'=>'Falha interna']);
}
