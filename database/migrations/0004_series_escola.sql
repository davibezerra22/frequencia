ALTER TABLE series
  ADD COLUMN escola_id INT UNSIGNED NULL,
  ADD KEY idx_series_escola (escola_id),
  ADD CONSTRAINT fk_series_escola FOREIGN KEY (escola_id) REFERENCES escolas(id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE series DROP INDEX uniq_series_nome;
ALTER TABLE series ADD UNIQUE KEY uniq_series_escola_nome (escola_id, nome);

-- Backfill series.escola_id via turmas mapping
UPDATE series s
JOIN turmas t ON t.serie_id = s.id
SET s.escola_id = t.escola_id
WHERE s.escola_id IS NULL;
