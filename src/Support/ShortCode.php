<?php
namespace App\Support;
class ShortCode {
  private const BASE32_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // Crockford base32, sem I/L/O/U
  public static function b32enc(int $n): string {
    if ($n <= 0) return '0';
    $out = '';
    while ($n > 0) { $out = self::BASE32_ALPHABET[$n % 32] . $out; $n = intdiv($n, 32); }
    return $out;
  }
  public static function checksum(string $s): string {
    $sum = 0;
    for ($i=0;$i<strlen($s);$i++) { $sum = ($sum + ord($s[$i])) % 32; }
    return self::BASE32_ALPHABET[$sum];
  }
  public static function hmacShort(string $secret, string $data, int $len=4): string {
    $h = hash_hmac('sha256', $data, $secret);
    $b = strtoupper($h);
    $out = '';
    for ($i=0;$i<$len;$i++) {
      $chunk = hexdec(substr($b, $i*2, 2)) % 32;
      $out .= self::BASE32_ALPHABET[$chunk];
    }
    return $out;
  }
  public static function makeCode(int $escolaId, int $alunoId, string $secret): string {
    // Código curto de 7 caracteres: 1 (escola) + 4 (aluno) + 2 (verificação)
    $Efull = self::b32enc($escolaId);
    $E = substr($Efull, -1); // 1 char
    $Afull = self::b32enc($alunoId);
    $Abody = substr($Afull, -4); // 4 chars (se menor, tudo)
    $Abody = str_pad($Abody, 4, '0', STR_PAD_LEFT);
    $K = self::hmacShort($secret, $E.'|'.$Abody, 2); // 2 chars
    return $E.$Abody.$K; // total 7 chars
  }
}
