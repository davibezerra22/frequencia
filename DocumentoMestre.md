# 📑 DOCUMENTO MESTRE: Sistema de Frequência Escolar Inteligente (V 5.0)

## 1. ESCOPO E OBJETIVO
Desenvolver um sistema local (inicialmente, deixando pronto para um futuro deploy) para controle de frequência de 500+ alunos, utilizando QR Code como método de identificação inicial. O sistema foca em velocidade de operação no portão, autonomia da gestão no calendário e automação de tarefas burocráticas para professores via extensão de navegador.

## 2. ARQUITETURA DE DADOS (MySQL)
O banco de dados deve seguir a hierarquia relacional para garantir integridade e histórico anual:

- `escolas`: ID, nome, logotipo.
- `anos_letivos`: ID, escola_id, ano (ex: 2024), status (ativo/inativo).
- `periodos_letivos`: ID, ano_letivo_id, nome (ex: 1º Bimestre), data_inicio, data_fim. (Definidos manualmente pelo usuário).
- `series`: ID, nome (ex: 1º Ano, 2º Ano).
- `turmas`: ID, serie_id, ano_letivo_id, nome (ex: Turma A, Turma B).
- `alunos`: ID, nome, matricula (UNIQUE), foto_aluno, qrcode_hash.
- `matriculas_turma`: ID, aluno_id, turma_id (vínculo do aluno ao ano/turma vigente).
- `frequencias`: ID, aluno_id, turma_id, data, hora, status (presente, falta, justificado), justificativa_texto, usuario_registro_id.
- `configuracoes`: ID, chave, valor (ex: horario_relatorio_diario).

## 3. MÓDULOS DO SISTEMA

### 3.1. Portal Administrativo (`/adminfrequencia`)
- Acesso: Protegido por login (Nível: Admin/Gestão).
- Gestão de Calendário: Interface para criar o Ano Letivo e cadastrar manualmente as datas de início e fim dos períodos (bimestres).
- Importação Otimizada: Upload de arquivo CSV para cadastro massivo de alunos e enturmação automática.
- Gestão de Biometria Visual: Interface rápida para vincular fotos aos alunos cadastrados (essencial para o anti-fraude).
- Relatórios e Justificativas:
  - Módulo para inserir justificativas de faltas (atestados).
  - Geração de relatórios de frequência filtrados por Período (Bimestre) com base nas datas cadastradas, relatórios por aluno, por turma, por dia, por justificativa e demais tipos de relatórios.
  - Os relatórios devem serão criados de acordo com a nescessidade. O importante é o sistema ter um bom controle desses dados no banco de dados para ser possível gerar relatórios precisos e eficientes.
- Automação de Avisos: Script que gera um resumo de faltas formatado para compartilhamento em grupos (WhatsApp) em horário configurável.

#### 3.1.1. Gerador de Identidade Estudantil (Crachás)
- Densidade do QR Code: Codificar apenas o ID/Matrícula do aluno para garantir módulos grandes e leitura rápida.
- Layout de Impressão:
  - Tamanho padrão cartão de crédito (85mm x 55mm).
  - Cabeçalho: Nome da Escola, Nome do Aluno, Série e Turma.
  - Organização: 8 cartões por folha A4 (2x4).
- Exportação: Gerar arquivo PDF otimizado para impressão direta.

### 3.2. Módulo Totem de Leitura (`/leitorfrequencia`)
- Acesso: Login obrigatório para operadores (Professor/Monitor).
- Tecnologia de Leitura: Uso da biblioteca Html5-QRCode (compatibilidade garantida com Android, iOS e Webcams).
- Fluxo de UX (User Experience):
  - Scanner ativo e persistente em tela cheia.
  - Leitura do QR Code enviada via AJAX para o backend.
  - Mecanismo Anti-Fraude: Exibição imediata da Foto do Aluno em destaque, nome e turma.
  - Feedback Sonoro: Uso da Web Audio API (Bip agudo para sucesso / Bip grave para erro ou duplicidade).
  - Auto-Reset: A interface limpa e reinicia o scanner após 2 segundos.

### 3.3. Extensão de Navegador (`/extensao-professor`)
- Tecnologia: Manifest V3 (Chrome Extension).
- Objetivo: Ponte entre o sistema local e o sistema oficial externo.
- Funcionalidade:
  - Ao abrir a chamada no sistema externo, a extensão consulta a API do localhost(ou do sistema WEB quando o deploy for feito).
  - Identifica os faltosos do dia e marca automaticamente os campos de "Falta" no sistema oficial via manipulação do DOM.

## 4. REQUISITOS TÉCNICOS E SEGURANÇA
- Linguagem: PHP 8.x (PDO) e MySQL.
- Frontend: HTML5, CSS3 (Responsivo/Mobile-first), JS Vanilla.
- Performance: Resposta do registro de frequência em < 500ms.
- Segurança: Uso de Prepared Statements contra SQL Injection; validação de duplicidade de registro no mesmo turno.
- Hardware: Deve funcionar em qualquer navegador moderno com acesso à câmera.

## 5. ORIENTAÇÕES PARA O DESENVOLVIMENTO (TRAE Solo)
- Prioridade 1: Estrutura de banco de dados e diretórios.
- Prioridade 2: CRUD de períodos, turmas e importador CSV de alunos.
- Prioridade 3: Interface do Totem com feedback visual (foto) e sonoro.
- Prioridade 4: Relatórios parametrizados por períodos definidos pelo usuário.
- Prioridade 5: API básica para consumo da Extensão de Navegador.

## 6. REGRAS DE NEGÓCIO E CICLO LETIVO
- Vínculo Anual: O aluno possui um cadastro único, mas sua participação em uma classe é definida pela tabela `matriculas_turma`. 
- Validação no Totem: O leitor de QR Code só deve registrar presença se o aluno possuir uma matrícula ativa no `ano_letivo` atual.
- Histórico: O sistema deve permitir consultar faltas de anos anteriores filtrando pelo `ano_letivo_id` correspondente.
- Gestão de Fluxo (Enturmação): O sistema deve prever uma funcionalidade para "promover" alunos de uma turma para outra na virada do ano, mantendo o histórico de anos passados intacto.

### 6.1. Controle de Presença por Turno (Tempo Integral e Semiturno)
- Definição: “Turno” representa janelas operacionais (ex.: manhã, tarde, noite) usadas para validar registros e evitar duplicidades.
- Modos de registro (configurável por escola/turma/ano letivo):
  - Diário integral: um registro por `data` para cada `aluno_id` e `turma_id`. Índice sugerido: `UNIQUE (aluno_id, turma_id, data)`.
  - Diário por turno: dois ou mais registros por `data`, separados por `turno` (`manha`, `tarde`, opcional `noite`). Índice sugerido: `UNIQUE (aluno_id, turma_id, data, turno)`.
- Configuração:
  - Em `configuracoes`, chave `controle_presenca_modo` com valores `diario_integral` ou `diario_por_turno`.
  - Janelas de turno: `turno_manha_inicio`, `turno_manha_fim`, `turno_tarde_inicio`, `turno_tarde_fim` (opcional `turno_noite_inicio`, `turno_noite_fim`).
- Validação de duplicidade:
  - Diário integral: bloquear inserção se já houver registro para `aluno_id`, `turma_id` e `data`.
  - Diário por turno: resolver o `turno` a partir da `hora` e das janelas configuradas; bloquear inserção se já houver registro para o mesmo `turno` na mesma `data`.
- Auditoria e operação:
  - Registrar `usuario_registro_id` e dispositivo do Totem; usar feedback sonoro diferenciado para sucesso/duplicidade.
  - Cache leve de janelas de turno no Totem e invalidação automática quando `configuracoes` forem alteradas.

## 7. BOAS PRÁTICAS E ESTABILIDADE (⚠️ IMPORTANTE)
- **Modularização:** Código separado por responsabilidade. Não reescreva módulos validados.
- **Segurança:** Prepared Statements obrigatórios.
- **Escalabilidade:** O sistema deve suportar novos campos sem quebrar a estrutura existente.
