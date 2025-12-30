# Sistema de Frequência Escolar (Multi-Escola)

Aplicação web para gestão de presença e cadastro de alunos, turmas, séries e períodos letivos, pronta para operar em múltiplas escolas com isolamento por instituição.

## Principais Recursos
- Multi-tenant: cada escola acessa apenas seus dados
- Superadmin: cria, ativa/inativa e gerencia escolas
- Usuários por escola: admin, gestão e operador
- Períodos Letivos: anos e períodos com calendário pt‑BR
- Séries e Turmas: criação/edição e visualização por ano atual
- Alunos: cadastro individual, edição, exclusão e enturmação
- Importação CSV de alunos: nome;matricula;foto
- UI moderna com modais padronizados (side bar, layout responsivo)

## Arquitetura
- Linguagem: PHP 8.4
- Banco: MySQL 8.4
- Servidor de desenvolvimento: `php -S`
- Padrão de configuração: arquivo `.env` (excluído do repositório)

## Estrutura
- `public/adminfrequencia/` páginas administrativas (dashboard, períodos, séries/turmas, alunos, enturmação, usuários, escolas)
- `database/migrations/` migrações SQL (schema e evoluções)
- `src/` bootstrap e conexões
- `.gitignore` exclui `.env`, `.mysql/` e diretórios locais

## Instalação e Execução (Windows/PowerShell)
1. Iniciar MySQL (ajuste caminhos conforme sua instalação):
   ```powershell
   & "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" `
     --datadir="c:\Users\Suporte\Documents\Desenvolvimento\Frequencia\.mysql\data2" `
     --basedir="C:\Program Files\MySQL\MySQL Server 8.4" `
     --port=3308 --bind-address=127.0.0.1 --console
   ```
2. Criar banco e aplicar migrações:
   ```powershell
   & "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" `
     -h 127.0.0.1 -P 3308 -u root -e "CREATE DATABASE IF NOT EXISTS frequencia CHARACTER SET utf8mb4;"

   Get-Content -Raw ".\database\migrations\0001_initial.sql" | `
     & "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" -h 127.0.0.1 -P 3308 -u root -D frequencia
   Get-Content -Raw ".\database\migrations\0002_usuarios.sql" | `
     & "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" -h 127.0.0.1 -P 3308 -u root -D frequencia
   Get-Content -Raw ".\database\migrations\0003_multitenant.sql" | `
     & "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" -h 127.0.0.1 -P 3308 -u root -D frequencia
   Get-Content -Raw ".\database\migrations\0004_series_escola.sql" | `
     & "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" -h 127.0.0.1 -P 3308 -u root -D frequencia
   ```
3. Configurar `.env` (exemplo):
   ```
   DB_HOST=127.0.0.1
   DB_PORT=3308
   DB_NAME=frequencia
   DB_USER=root
   DB_PASS=
   TIMEZONE=America/Sao_Paulo
   ```
4. Iniciar servidor PHP:
   ```powershell
   & "C:\Users\Suporte\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" `
     -d extension_dir="C:\Users\Suporte\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\ext" `
     -d extension=pdo_mysql `
     -d date.timezone=America/Sao_Paulo `
     -S 127.0.0.1:8000 -t public
   ```

## Fluxo de Acesso
- Superadmin:
  - Crie a escola e seu admin inicial em “Escolas”
  - Defina “Ano atual” em “Períodos”
  - Use seletores de escola em “Séries e Turmas”, “Alunos” e “Enturmação” para visualizar dados por instituição
- Admin da escola:
  - Consegue criar usuários da própria escola em “Usuários”
  - Gerencia períodos, séries, turmas e alunos apenas da sua escola

## Importação CSV de Alunos
- Formato: `nome;matricula;foto` (separador `;`)
- Cabeçalho opcional na primeira linha
- Página: Admin • Importar Alunos

## Observações
- Dados locais do banco (`.mysql/`) e credenciais (`.env`) não são versionados (gitignore).
- Migrações versionam o schema; ajuste conforme suas necessidades.

## Licença
Este projeto é fornecido conforme os termos definidos pelo autor. Ajuste uma licença (ex.: MIT) se necessário.
