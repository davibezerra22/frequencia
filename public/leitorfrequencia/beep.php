<?php
$type = $_GET['type'] ?? 'ok';
$freq = ($type === 'err') ? 220 : 960;
$duration = ($type === 'err') ? 0.26 : 0.18; // seconds
$rate = 44100;
$samples = (int)($duration * $rate);
$ampl = 0.5;
$data = '';
for ($i = 0; $i < $samples; $i++) {
  $t = $i / $rate;
  $val = sin(2 * M_PI * $freq * $t);
  $s = (int)max(min($val * 32767 * $ampl, 32767), -32768);
  $data .= pack('v', $s & 0xFFFF);
}
$datalen = strlen($data);
$wav =
  "RIFF" .
  pack('V', 36 + $datalen) .
  "WAVEfmt " .
  pack('V', 16) .           // PCM header size
  pack('v', 1) .            // PCM format
  pack('v', 1) .            // channels
  pack('V', $rate) .        // sample rate
  pack('V', $rate * 2) .    // byte rate (16-bit mono)
  pack('v', 2) .            // block align
  pack('v', 16) .           // bits per sample
  "data" .
  pack('V', $datalen) .
  $data;
header('Content-Type: audio/wav');
header('Cache-Control: no-store');
header('Content-Length: '.strlen($wav));
echo $wav;
