@echo off
cd /d "%~dp0"
where node >nul 2>&1
if errorlevel 1 (
  echo Node.js nao encontrado. Abrindo somente neste navegador.
  start "" "%~dp0index.html"
  pause
  exit /b 1
)
node server.js --open
pause
