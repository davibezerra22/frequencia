# Objetivo

Preparar geração de QR a partir de código curto, emissão em PDF por turma ou individual, com escopo por escola, e validar registro de frequência.

## Código Curto do QR

1. Formato

* `QRS1-<E>-<C>-<K>` onde:

  * `E`: escola\_id em base32 (Crockford), 2–4 chars

  * `C`: código curto do aluno (base32 de aluno\_id com checksum), 4–8 chars

  * `K`: chave curta de verificação (HMAC SHA256 truncado para 4–6 chars alfanuméricos)

* Exemplo: `QRS1-3G-9PJ2-7XM`

1. Propriedades

* Pequeno e legível; boa taxa de leitura em QR de baixa densidade

* Independente de matrícula (evita números longos), mas derivável do aluno\_id

* Checagem rápida via checksum + HMAC para evitar falsificação

## Geração dos QR

1. Serviço de geração

* Endpoint admin: `POST /admin/qrcode/gerar` com parâmetros `{tipo: 'turma'|'aluno', id, layout: 'etiqueta'|'cartao'}`

* Back-end monta payload curto e gera imagem QR (PNG/WebP)

1. Persistência

* Armazena o código curto em `alunos.codigo_curto` (novo campo) e arquivo em `/uploads/qrcodes/escola_<id>/aluno_<id>.png`

* Índice único: `(escola_id, codigo_curto)`

## Emissão em PDF

1. Individual

* `GET /admin/qrcode/pdf?aluno_id=<id>`: A6/A7 com QR grande, nome e turma

1. Por Turma

* `GET /admin/qrcode/pdf?turma_id=<id>`: A4 com grade (3×4) de cartões com QR e nome

* Opção “etiquetas” (padrão 62×20mm) em grade

1. Biblioteca

* `dompdf/dompdf` ou `mpdf/mpdf` para PDFs; confirmar disponibilidade

## Escopo por Escola

* Permissões: admin da escola só gera para seus alunos/turmas (validação de `escola_id` na consulta)

* Superadmin pode selecionar escola; endpoint verifica escopo antes de gerar

## Validação em Leitura

1. Totem decodifica QR → `QRS1-...`
2. Validação

* Decodificar base32 para `escola_id` e `aluno_id`

* Conferir `K` (HMAC truncado) com segredo do servidor

* Verificar aluno pertence à escola e tem enturmação no Ano atual

* Recuperar `turma_id`, `serie_id`; bloquear duplicada em janela N segundos

## Tabela de Frequência

* `frequencias(id, aluno_id, turma_id, serie_id, escola_id, periodo_id NULL, leitura_at TIMESTAMP, origem, device_id, status)`

* Índices: `aluno_id+leitura_at`, `turma_id+leitura_at`, `escola_id+leitura_at`

* Migração idempotente e seeds mínimos para teste

## API do Totem

* `POST /api/frequencia/leitura` com `{qr, device_id}`

* Resposta com foto/nome e status (`ok|duplicada|erro`)

* Rate limit por `device_id`; logs de auditoria

## Administração

* Página “QR Codes”

  * Selecionar escola/turma/aluno

  * Botões: Gerar Imagens, Gerar PDF (individual/turma)

  * Histórico/monitor de leituras recentes

## Testes e Validação

* Unitários: base32, checksum, HMAC truncado

* Integração: emissão PDF/PNG, API de leitura escrevendo em `frequencias`

* E2E: geração por turma, leitura no Totem, relatório por dia

## Próximos Passos

1. Adicionar `alunos.codigo_curto` e índices; criar `frequencias`
2. Implementar utilitários de base32/checksum/HMAC e geração de QR curto
3. Endpoints de geração (imagens + PDF) com escopo por escola
4. API de leitura e bloqueio de duplicadas
5. Página admin de QR Codes; preparar Totem (Prioridade 3) para feedback visual/sonoro

