PHP

- Abrir um terminal na pasta do projeto: c:\Users\Suporte\Documents\Desenvolvimento\Frequencia
- Iniciar servidor: & "C:\Users\Suporte\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" -d extension_dir="C:\Users\Suporte\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\ext" -d extension=pdo_mysql -d date.timezone=America/Sao_Paulo -S 127.0.0.1:8000 -t public
- Acessar: http://127.0.0.1:8000/ e http://127.0.0.1:8000/adminfrequencia/
- Encerrar: Ctrl+C no terminal do servidor
MySQL

- Abrir outro terminal
- Iniciar servidor: & "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --datadir="c:\Users\Suporte\Documents\Desenvolvimento\Frequencia\.mysql\data" --basedir="C:\Program Files\MySQL\MySQL Server 8.4" --port=3307 --bind-address=127.0.0.1 --console
- Cliente para testar: & "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" -h 127.0.0.1 -P 3307 -u root
- Encerrar: Ctrl+C no terminal do MySQL (como está em modo console)