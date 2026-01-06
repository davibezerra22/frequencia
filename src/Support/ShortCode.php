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
    $E = self::b32enc($escolaId);
    $Araw = self::b32enc($alunoId);
    $C = $Araw . self::checksum($Araw);
    $K = self::hmacShort($secret, $E.'|'.$C, 4);
    return 'QRS1-'.$E.'-'.$C.'-'.$K;
  }
}
