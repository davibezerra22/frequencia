<?php
$root = getenv('LOCALAPPDATA') ? (getenv('LOCALAPPDATA') . DIRECTORY_SEPARATOR . 'mkcert' . DIRECTORY_SEPARATOR . 'rootCA.pem') : '';
if (!$root || !is_file($root)) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Certificado CA do mkcert não encontrado'; exit; }
$pem = file_get_contents($root);
if (!preg_match('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $pem, $m)) {
  http_response_code(500); header('Content-Type: text/plain; charset=utf-8'); echo 'Conteúdo do certificado inválido'; exit;
}
$der = base64_decode(str_replace(["\r","\n"," "], '', $m[1]));
header('Content-Type: application/pkix-cert');
header('Content-Disposition: attachment; filename="mkcert-rootCA.cer"');
echo $der;
