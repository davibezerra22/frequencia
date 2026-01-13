## Objetivo

* Tornar o sistema multi-tenant (multi-escola) com isolamento de dados, controle por usuário e um superusuário que gerencia escolas.

## Modelo de Dados

* **escolas**: já existe; adicionar `status` (ativo/inativo), `slug` e campos de contato.

* **usuarios**: adicionar `escola_id` (nullable); regras:

  * `escola_id != NULL` → usuário da escola, vê apenas dados da sua escola.

  * `escola_id = NULL` e `role = 'superadmin'` → super usuário que gerencia todas as escolas.

* **anos\_letivos**, **turmas**, **alunos**, **frequencias**:

  * Adicionar `escola_id` e índices (`KEY escola_id`);

  * Garantir FKs coerentes: ex. `anos_letivos.escola_id` já existe; para relacionados que dependem de ano/turma/aluno, o `escola_id` pode ser deduzido, mas manter coluna direta simplifica filtros e auditoria.

* **configuracoes**: optar por duas camadas:

  * Globais (sem `escola_id`), por exemplo parâmetros de sistema.

  * Por escola (adicionar `escola_id`), chaves como `ano_letivo_atual_id` por escola.

## Autenticação e Sessão

* Campos de sessão: `user_id`, `role`, `escola_id` (nullable), `permissions`.

* Fluxo de login:

  * Usuário de escola: carrega `escola_id` e bloqueia acesso fora do escopo.

  * Superadmin: sem `escola_id`; navega em painel de administração de escolas.

* Middleware (`_auth.php`):

  * Verifica sessão.

  * Injeta `$_SESSION['escola_id']` em um *contexto de request*.

  * Nova função `require_role($roles)` para rotas críticas.

## Isolamento por Consulta

* Criar utilitário `Scope::applyEscola($query, $params, $session_escola_id)` que:

  * Quando `escola_id` presente, adiciona `WHERE escola_id = ?`.

  * Para joins, usar `WHERE` na tabela raiz e manter integridade com FKs.

* Regras por página:

  * Painéis da escola (Períodos, Turmas, Alunos, Enturmação, Frequências): sempre filtrar por `escola_id` da sessão.

  * Superadmin: telas especiais sem filtro, com seletor de escola e ações de ativar/desativar.

## Autorização e Perfis

* Perfis: `superadmin`, `admin`, `gestao`, `operador`.

* **superadmin** (global): CRUD de escolas, ativação, auditoria.

* **admin** (da escola): configurações (anos, séries, turmas), usuários da sua escola.

* **gestao/operador**: escopo reduzido (ex.: registro de frequência, consultas).

* Mapear permissões em um array (ex.: `can_manage_escolas`, `can_manage_usuarios`, `can_manage_periodos`, `can_register_frequencias`), resolvido por `role`.

## Navegação e UX

* Sidebar dinâmica:

  * Usuário de escola: itens da escola.

  * Superadmin: item “Escolas” (CRUD com status), relatório geral.

* Contexto atual exibido no topo (Escola, Ano atual) com modal.

* Em superadmin, adicionar seletor rápido de escola para visualizar dados.

## Migrações

* Adicionar colunas e índices:

  * `ALTER TABLE usuarios ADD escola_id INT UNSIGNED NULL, ADD CONSTRAINT fk_usuarios_escola FOREIGN KEY (escola_id) REFERENCES escolas(id) ON DELETE SET NULL;`

  * `ALTER TABLE escolas ADD status ENUM('ativo','inativo') DEFAULT 'ativo', ADD slug VARCHAR(64) UNIQUE;`

  * `ALTER TABLE alunos ADD escola_id INT UNSIGNED NOT NULL;`

  * `ALTER TABLE turmas ADD escola_id INT UNSIGNED NOT NULL;`

  * `ALTER TABLE frequencias ADD escola_id INT UNSIGNED NOT NULL;`

  * `ALTER TABLE configuracoes ADD escola_id INT UNSIGNED NULL, ADD KEY idx_config_escola (escola_id);`

* Backfill: popular `escola_id` a partir de FK pai (ex.: turmas → anos\_letivos → escola; alunos e frequências seguindo a hierarquia).

## Segurança e Validações

* Em cada POST, validar que o recurso pertence à `escola_id` do usuário.

* Responder 403 se tentar operar fora do escopo.

* Ao excluir escola (apenas superadmin), usar `inativo` ao invés de deletar; impedir criação de dados novos quando inativa.

## Testes e Verificações

* Criar fixtures com 2 escolas e usuários por escola + superadmin.

* Testar que um usuário de Escola A não enxerga dados de Escola B.

* Testar que superadmin acessa CRUD de escolas e alterna visualização.

## Passos de Implementação

1. Migrações: novas colunas e FKs; `configuracoes` por escola.
2. Atualizar sessão/login para incluir `escola_id` e `role`.
3. Criar utilitários de escopo e autorização; adaptar páginas para filtros por `escola_id`.
4. Adicionar painel de superadmin (CRUD de escolas + usuários por escola).
5. Backfill dos dados existentes e validação.
6. Testes manuais e automatizados para isolamento.

## Considerações Futuras

* Rate limits por escola, planos e limites.

* Logs/auditoria por `escola_id`.

* Multi banco (sharding) se crescimento exigir, mantendo compatibilidade com abstração de escopo.

