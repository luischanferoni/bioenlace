@echo off
echo ========================================
echo Diagnostico de la carpeta web
echo ========================================
echo.

cd /d "%~dp0"

echo [1] Verificando ubicacion del repositorio Git...
git rev-parse --show-toplevel 2>nul
if errorlevel 1 (
    echo ERROR: No se encuentra un repositorio Git.
    echo Ejecutando git init...
    git init
    git branch -M main
)

echo.
echo [2] Verificando archivos de web en Git...
echo.
echo Archivos de web rastreados por Git:
git ls-files | findstr /C:"^web/" | head -20
if errorlevel 1 (
    echo ADVERTENCIA: No se encontraron archivos de web rastreados por Git.
)

echo.
echo [3] Verificando estructura de archivos en disco...
echo.
if exist web (
    echo Carpeta web existe en disco.
    echo Contando archivos en web...
    dir /s /b web\*.* 2>nul | find /c /v "" > temp_count.txt
    set /p file_count=<temp_count.txt
    echo Aproximadamente %file_count% archivos encontrados en web.
    del temp_count.txt 2>nul
) else (
    echo ERROR: La carpeta web no existe!
)

echo.
echo [4] Verificando .gitignore...
if exist .gitignore (
    echo .gitignore existe.
    echo Reglas relacionadas con web:
    findstr /C:"web" .gitignore
) else (
    echo ADVERTENCIA: No existe .gitignore en el directorio raiz.
)

echo.
echo [5] Estado actual de Git...
git status --short | head -30

echo.
echo ========================================
echo Diagnostico completado
echo ========================================
pause

