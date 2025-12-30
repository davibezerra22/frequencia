<?php
require_once __DIR__ . '/../../src/Support/Env.php';
require_once __DIR__ . '/../../src/Config/Database.php';
require_once __DIR__ . '/../../src/Database/Connection.php';
require_once __DIR__ . '/../../src/Bootstrap.php';
use App\Database\Connection;
header('Content-Type: application/json; charset=utf-8');
session_start();
$usuario = $_POST['usuario'] ?? '';
$senha = $_POST['senha'] ?? '';
try {
    $pdo = Connection::get();
    $sth = $pdo->prepare('SELECT u.id, u.nome, u.senha_hash, u.role, u.status, u.escola_id, e.status AS escola_status, e.nome AS escola_nome, e.logotipo AS escola_logo
                          FROM usuarios u
                          LEFT JOIN escolas e ON e.id = u.escola_id
                          WHERE u.usuario=? LIMIT 1');
    $sth->execute([$usuario]);
    $u = $sth->fetch();
    if (!$u || $u['status'] !== 'ativo') {
        http_response_code(401);
        echo json_encode(['status'=>'error','message'=>'Usuário inválido ou inativo']);
        exit;
    }
    if (!empty($u['escola_id']) && ($u['escola_status'] ?? 'ativo') !== 'ativo') {
        http_response_code(403);
        echo json_encode(['status'=>'error','message'=>'Escola inativa']);
        exit;
    }
    if (!password_verify($senha, $u['senha_hash'])) {
        http_response_code(401);
        echo json_encode(['status'=>'error','message'=>'Senha inválida']);
        exit;
    }
    $_SESSION['user_id'] = (int)$u['id'];
    $_SESSION['user_name'] = $u['nome'];
    $_SESSION['user_role'] = $u['role'];
    $_SESSION['escola_id'] = isset($u['escola_id']) ? (int)$u['escola_id'] : null;
    $_SESSION['escola_nome'] = isset($u['escola_nome']) ? (string)$u['escola_nome'] : '';
    $_SESSION['escola_logo'] = isset($u['escola_logo']) ? (string)$u['escola_logo'] : '';
    echo json_encode(['status'=>'ok']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Falha no servidor']);
}
