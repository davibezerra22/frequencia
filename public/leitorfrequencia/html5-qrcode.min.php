<?php
header('Content-Type: application/javascript; charset=utf-8');
$dir = __DIR__;
$cache = $dir . '/html5-qrcode.min.cache.js';
$srcs = [
  'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.9/minified/html5-qrcode.min.js',
  'https://unpkg.com/html5-qrcode@2.3.9/minified/html5-qrcode.min.js',
];
function tryFetch($url){
  try {
    $ctx = stream_context_create(['http'=>['timeout'=>6],'https'=>['timeout'=>6]]);
    $js = @file_get_contents($url, false, $ctx);
    if ($js && strpos($js, 'Html5Qrcode') !== false) return $js;
  } catch (\Throwable $e) {}
  return null;
}
if (is_file($cache) && filesize($cache) > 1024) {
  readfile($cache);
  exit;
}
foreach ($srcs as $u) {
  $js = tryFetch($u);
  if ($js) {
    @file_put_contents($cache, $js);
    echo $js;
    exit;
  }
}
echo "console.error('Html5-Qrcode indisponível');";
