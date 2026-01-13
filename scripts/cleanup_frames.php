<?php
require_once __DIR__ . '/../src/Support/Env.php';
require_once __DIR__ . '/../src/Database/Connection.php';
require_once __DIR__ . '/../src/Bootstrap.php';
use App\Support\Env;
use App\Database\Connection;
$pdo = Connection::get();
$days = (int)Env::get('FRAMES_RETENTION_DAYS','3');
if ($days <= 0) $days = 3;
$dir = Env::get('FRAMES_STORAGE_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'frames');
$cutTs = time() - ($days * 86400);
function removeOldFiles(string $base, int $cutTs): int {
  $removed = 0;
  if (!is_dir($base)) return 0;
  $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
  foreach ($iter as $path) {
    if ($path->isFile()) {
      $mt = $path->getMTime();
      if ($mt < $cutTs) {
        @unlink($path->getPathname());
        $removed++;
      }
    }
    // remove diretórios vazios
    if ($path->isDir()) {
      @rmdir($path->getPathname());
    }
  }
  return $removed;
}
$removed = removeOldFiles($dir, $cutTs);
try {
  $stmt = $pdo->prepare("DELETE FROM frequencias_fotos WHERE created_at < (NOW() - INTERVAL ? DAY)");
  $stmt->execute([$days]);
} catch (\Throwable $e) {}
echo "Removidos arquivos: $removed\n";
