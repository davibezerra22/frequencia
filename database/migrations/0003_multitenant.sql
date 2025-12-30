-- Multi-escola: adicionar colunas, índices e FKs

ALTER TABLE escolas
  ADD COLUMN status ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  ADD COLUMN slug VARCHAR(64) NULL,
  ADD UNIQUE KEY uniq_escolas_slug (slug);

ALTER TABLE usuarios
  ADD COLUMN escola_id INT UNSIGNED NULL,
  ADD CONSTRAINT fk_usuarios_escola FOREIGN KEY (escola_id) REFERENCES escolas(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE configuracoes
  ADD COLUMN escola_id INT UNSIGNED NULL,
  ADD KEY idx_config_escola (escola_id),
  ADD CONSTRAINT fk_config_escola FOREIGN KEY (escola_id) REFERENCES escolas(id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE turmas
  ADD COLUMN escola_id INT UNSIGNED NULL,
  ADD KEY idx_turmas_escola (escola_id),
  ADD CONSTRAINT fk_turmas_escola FOREIGN KEY (escola_id) REFERENCES escolas(id) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE alunos
  ADD COLUMN escola_id INT UNSIGNED NULL,
  ADD KEY idx_alunos_escola (escola_id),
  ADD CONSTRAINT fk_alunos_escola FOREIGN KEY (escola_id) REFERENCES escolas(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE frequencias
  ADD COLUMN escola_id INT UNSIGNED NULL,
  ADD KEY idx_frequencias_escola (escola_id),
  ADD CONSTRAINT fk_frequencias_escola FOREIGN KEY (escola_id) REFERENCES escolas(id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Backfill básico
-- turmas.escola_id a partir de anos_letivos
UPDATE turmas t
JOIN anos_letivos a ON a.id = t.ano_letivo_id
SET t.escola_id = a.escola_id
WHERE t.escola_id IS NULL;

-- frequencias.escola_id a partir de turmas
UPDATE frequencias f
JOIN turmas t ON t.id = f.turma_id
SET f.escola_id = t.escola_id
WHERE f.escola_id IS NULL;

-- alunos.escola_id a partir de enturmação quando possível
UPDATE alunos al
JOIN matriculas_turma mt ON mt.aluno_id = al.id
JOIN turmas t ON t.id = mt.turma_id
SET al.escola_id = t.escola_id
WHERE al.escola_id IS NULL;

-- configuracoes por escola: mover chaves dependentes
-- Ano letivo atual por escola; se existir valor global, copiar para escola atual definida
INSERT INTO configuracoes (escola_id, chave, valor)
SELECT CAST(v.valor AS UNSIGNED) AS escola_id, 'ano_letivo_atual_id', c.valor
FROM configuracoes c
JOIN configuracoes v ON v.chave='escola_atual_id'
WHERE c.chave='ano_letivo_atual_id'
ON DUPLICATE KEY UPDATE valor=VALUES(valor);
