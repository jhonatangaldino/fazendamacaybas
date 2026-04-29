@echo off
REM =============================================================
REM backup-local.bat — Baixa backup do servidor pro PC.
REM Clique duas vezes neste arquivo para rodar manualmente
REM OU agende via Task Scheduler do Windows.
REM
REM Pré-requisitos:
REM   - Git for Windows instalado (vem com bash.exe)
REM   - Chave SSH em %USERPROFILE%\.ssh\macaybas_deploy
REM =============================================================

REM Localiza o bash do Git for Windows
set "BASH_EXE=C:\Program Files\Git\bin\bash.exe"
if not exist "%BASH_EXE%" set "BASH_EXE=C:\Program Files (x86)\Git\bin\bash.exe"
if not exist "%BASH_EXE%" (
    echo.
    echo [ERRO] Git Bash nao encontrado em C:\Program Files\Git\bin\bash.exe
    echo Instale Git for Windows: https://git-scm.com/download/win
    pause
    exit /b 1
)

echo.
echo =============================================================
echo  Backup Local · Fazenda Macaybas
echo  Baixando backup do servidor para C:\Users\%USERNAME%\backups-macaybas
echo =============================================================
echo.

REM Vai pra pasta do projeto (relativo a esse .bat)
cd /d "%~dp0\.."

REM Roda o script bash
"%BASH_EXE%" -lc "bash scripts/backup-baixar-local.sh"

set EXITCODE=%ERRORLEVEL%
echo.
if %EXITCODE% EQU 0 (
    echo  Backup concluido com sucesso.
) else (
    echo  Backup falhou — codigo %EXITCODE%
)
echo.
echo Pressione qualquer tecla para fechar...
pause >nul
exit /b %EXITCODE%
