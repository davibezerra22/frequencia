<?php
namespace App\Database;
use PDO;
class Ensure {
  public static function run(PDO $pdo): void {
    try {
      $pdo->exec("ALTER TABLE alunos ADD COLUMN IF NOT EXISTS codigo_curto VARCHAR(16) NULL");
    } catch (\Throwable $e) {
      try {
        $cols = $pdo->query("SHOW COLUMNS FROM alunos LIKE 'codigo_curto'")->fetch();
        if (!$cols) { $pdo->exec("ALTER TABLE alunos ADD COLUMN codigo_curto VARCHAR(16) NULL"); }
      } catch (\Throwable $e2) {}
    }
    try {
      $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uniq_aluno_codigo_curto ON alunos(escola_id, codigo_curto)");
    } catch (\Throwable $e) {
      // MySQL <= 8.0: check then create
      try {
        $idx = $pdo->query("SHOW INDEX FROM alunos WHERE Key_name='uniq_aluno_codigo_curto'")->fetch();
        if (!$idx) { $pdo->exec("CREATE UNIQUE INDEX uniq_aluno_codigo_curto ON alunos(escola_id, codigo_curto)"); }
      } catch (\Throwable $e2) {}
    }
    try {
      $pdo->exec("CREATE TABLE IF NOT EXISTS frequencias (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        aluno_id BIGINT UNSIGNED NOT NULL,
        turma_id BIGINT UNSIGNED NULL,
        serie_id BIGINT UNSIGNED NULL,
        escola_id BIGINT UNSIGNED NOT NULL,
        periodo_id BIGINT UNSIGNED NULL,
        leitura_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        origem VARCHAR(32) NOT NULL DEFAULT 'totem',
        device_id VARCHAR(64) NULL,
        status ENUM('ok','duplicada','fora_contexto') NOT NULL DEFAULT 'ok',
        INDEX idx_frequencias_aluno_leitura (aluno_id, leitura_at),
        INDEX idx_frequencias_turma_leitura (turma_id, leitura_at),
        INDEX idx_frequencias_escola_leitura (escola_id, leitura_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) {}
    // Backfill codigo_curto a partir da matricula
    try {
      $stmt = $pdo->query("SELECT id, escola_id, matricula FROM alunos");
      $rows = $stmt ? $stmt->fetchAll() : [];
      if ($rows) {
        $secret = \App\Support\Env::get('QR_SECRET','dev-secret');
        foreach ($rows as $r) {
          $id = (int)$r['id']; $eid = (int)$r['escola_id']; $mat = (int)$r['matricula'];
          if ($mat>0) {
            $code = \App\Support\ShortCode::makeCode($eid, $mat, $secret);
            try { $pdo->prepare("UPDATE alunos SET codigo_curto=? WHERE id=?")->execute([$code, $id]); } catch (\Throwable $e) {}
          }
        }
      }
    } catch (\Throwable $e) {}
  }
}
