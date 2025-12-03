@echo off
echo ========================================
echo Forzar agregado de archivos de web
echo ========================================
echo.
echo Este script eliminara web del indice de Git y lo volvera a agregar.
echo.
pause

cd /d "%~dp0"

echo [PASO 1] Eliminando web del indice de Git (sin eliminar archivos)...
git rm -r --cached web 2>nul
if errorlevel 1 (
    echo web no estaba en el indice, continuando...
) else (
    echo web eliminado del indice.
)

echo.
echo [PASO 2] Verificando que no existe .git dentro de web...
if exist web\.git (
    echo ADVERTENCIA: Existe .git dentro de web! Eliminando...
    rmdir /s /q web\.git
    echo .git eliminado de web.
)

echo.
echo [PASO 3] Agregando web nuevamente al indice...
git add web/
if errorlevel 1 (
    echo ERROR: No se pudieron agregar los archivos de web.
    pause
    exit /b 1
)

echo.
echo [PASO 4] Verificando archivos agregados...
git status --short | findstr /C:"web" | head -20
if errorlevel 1 (
    echo ADVERTENCIA: No se detectaron cambios en web.
) else (
    echo [OK] Archivos de web detectados en el staging area.
)

echo.
echo [PASO 5] Mostrando estadisticas...
git status --short | find /c /v ""
set /p total=<temp_count.txt 2>nul
echo Archivos en staging area: %total%

echo.
echo [PASO 6] Creando commit...
git commit -m "Fix: Reagregar archivos de web correctamente"
if errorlevel 1 (
    echo ADVERTENCIA: No se pudo crear el commit.
    echo Puede que no haya cambios o que necesites configurar usuario de Git.
    echo.
    echo Configura tu usuario de Git con:
    echo   git config user.name "Tu Nombre"
    echo   git config user.email "tu@email.com"
)

echo.
echo [PASO 7] Verificando conexion remota...
git remote -v
if errorlevel 1 (
    echo Configurando repositorio remoto...
    git remote add origin https://github.com/luischanferoni/bioenlace.git
)

echo.
echo ========================================
echo Proceso completado
echo ========================================
echo.
echo Para subir los cambios:
echo   git push origin main
echo.
echo O para forzar:
echo   git push -u origin main --force
echo.
pause

