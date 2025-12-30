CREATE TABLE IF NOT EXISTS escolas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(255) NOT NULL,
  logotipo VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS anos_letivos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  escola_id INT UNSIGNED NOT NULL,
  ano SMALLINT UNSIGNED NOT NULL,
  status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  CONSTRAINT fk_anos_escola FOREIGN KEY (escola_id) REFERENCES escolas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  UNIQUE KEY uniq_escola_ano (escola_id, ano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS periodos_letivos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ano_letivo_id INT UNSIGNED NOT NULL,
  nome VARCHAR(100) NOT NULL,
  data_inicio DATE NOT NULL,
  data_fim DATE NOT NULL,
  CONSTRAINT fk_periodos_ano FOREIGN KEY (ano_letivo_id) REFERENCES anos_letivos(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS series (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  UNIQUE KEY uniq_series_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS turmas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  serie_id INT UNSIGNED NOT NULL,
  ano_letivo_id INT UNSIGNED NOT NULL,
  nome VARCHAR(100) NOT NULL,
  CONSTRAINT fk_turmas_serie FOREIGN KEY (serie_id) REFERENCES series(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_turmas_ano FOREIGN KEY (ano_letivo_id) REFERENCES anos_letivos(id) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uniq_turma_nome_ano (ano_letivo_id, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alunos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(255) NOT NULL,
  matricula VARCHAR(50) NOT NULL,
  foto_aluno VARCHAR(255) NULL,
  qrcode_hash VARCHAR(64) NOT NULL,
  UNIQUE KEY uniq_alunos_matricula (matricula),
  UNIQUE KEY uniq_alunos_qrcode (qrcode_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS matriculas_turma (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  aluno_id INT UNSIGNED NOT NULL,
  turma_id INT UNSIGNED NOT NULL,
  CONSTRAINT fk_matriculas_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_matriculas_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uniq_matricula_aluno_turma (aluno_id, turma_id),
  KEY idx_matriculas_turma (turma_id),
  KEY idx_matriculas_aluno (aluno_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS frequencias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  aluno_id INT UNSIGNED NOT NULL,
  turma_id INT UNSIGNED NOT NULL,
  data DATE NOT NULL,
  hora TIME NOT NULL,
  turno ENUM('integral','manha','tarde','noite') NOT NULL DEFAULT 'integral',
  status ENUM('presente','falta','justificado') NOT NULL,
  justificativa_texto TEXT NULL,
  usuario_registro_id INT UNSIGNED NULL,
  CONSTRAINT fk_frequencias_aluno FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_frequencias_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uniq_frequencias_unica (aluno_id, turma_id, data, turno),
  KEY idx_frequencias_turma_data (turma_id, data),
  KEY idx_frequencias_aluno_data (aluno_id, data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(64) NOT NULL,
  valor VARCHAR(255) NOT NULL,
  UNIQUE KEY uniq_config_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO configuracoes (chave, valor) VALUES
('controle_presenca_modo', 'diario_por_turno')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);

INSERT INTO configuracoes (chave, valor) VALUES
('turno_manha_inicio', '07:00'),
('turno_manha_fim', '12:00'),
('turno_tarde_inicio', '13:00'),
('turno_tarde_fim', '18:00')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);

INSERT INTO configuracoes (chave, valor) VALUES
('horario_relatorio_diario', '18:30')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);
