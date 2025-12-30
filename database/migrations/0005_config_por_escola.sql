ALTER TABLE configuracoes
  DROP INDEX uniq_config_chave,
  ADD UNIQUE KEY uniq_config_escola_chave (escola_id, chave);

-- Migrar configuração global de ano atual para escola EEEP Pedro de Queiroz Lima, se aplicável
SET @eid := (SELECT id FROM escolas WHERE nome='EEEP Pedro de Queiroz Lima' LIMIT 1);
SET @ano := (SELECT valor FROM configuracoes WHERE escola_id IS NULL AND chave='ano_letivo_atual_id' LIMIT 1);
IF @eid IS NOT NULL AND @ano IS NOT NULL THEN
  INSERT INTO configuracoes (escola_id, chave, valor) VALUES (@eid, 'ano_letivo_atual_id', @ano)
  ON DUPLICATE KEY UPDATE valor=VALUES(valor);
END IF;
