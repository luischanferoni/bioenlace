@echo off
echo ========================================
echo Solucion completa para carpeta web
echo ========================================
echo.
echo Este script:
echo 1. Verificara la estructura
echo 2. Eliminara web del indice de Git
echo 3. Volvera a agregar web correctamente
echo 4. Creara un commit
echo 5. Mostrara instrucciones para subir
echo.
pause

cd /d "%~dp0"

echo.
echo ========================================
echo PASO 1: Verificaciones iniciales
echo ========================================
echo.

if not exist web (
    echo ERROR: La carpeta web no existe en este directorio!
    echo Asegurate de ejecutar este script desde d:\codigo\bioenlace\
    pause
    exit /b 1
)
echo [OK] Carpeta web existe.

if not exist .git (
    echo Inicializando repositorio Git...
    git init
    git branch -M main
    echo [OK] Repositorio Git inicializado.
) else (
    echo [OK] Repositorio Git existe.
)

if exist web\.git (
    echo ADVERTENCIA: Existe .git dentro de web! Eliminando...
    rmdir /s /q web\.git
    echo [OK] .git eliminado de web.
) else (
    echo [OK] No existe .git dentro de web.
)

echo.
echo ========================================
echo PASO 2: Limpiando indice de Git
echo ========================================
echo.

echo Eliminando web del indice de Git (sin borrar archivos)...
git rm -r --cached web/ 2>nul
if errorlevel 1 (
    echo web no estaba en el indice o ya fue eliminado.
) else (
    echo [OK] web eliminado del indice.
)

echo.
echo ========================================
echo PASO 3: Agregando archivos de web
echo ========================================
echo.

echo Agregando web/ al repositorio...
git add web/
if errorlevel 1 (
    echo ERROR: No se pudieron agregar los archivos.
    pause
    exit /b 1
)

echo Esperando un momento para que Git procese...
timeout /t 2 /nobreak >nul

echo.
echo Verificando archivos agregados...
git status --short web/ | find /c /v "" > temp_web_count.txt 2>nul
set /p web_count=<temp_web_count.txt 2>nul
del temp_web_count.txt 2>nul

if "%web_count%"=="" set web_count=0
if %web_count% GTR 0 (
    echo [OK] %web_count% archivos de web en staging area.
    echo.
    echo Mostrando primeros 20 archivos:
    git status --short web/ | head -20
) else (
    echo ADVERTENCIA: No se detectaron archivos de web.
    echo Verificando si web esta siendo ignorado...
    git check-ignore -v web/*.* | head -5
)

echo.
echo ========================================
echo PASO 4: Creando commit
echo ========================================
echo.

git commit -m "Fix: Reagregar archivos de web correctamente"
if errorlevel 1 (
    echo.
    echo ADVERTENCIA: No se pudo crear el commit.
    echo Posibles razones:
    echo - No hay cambios nuevos
    echo - Necesitas configurar usuario de Git
    echo.
    echo Configura tu usuario con:
    echo   git config user.name "Tu Nombre"
    echo   git config user.email "tu@email.com"
    echo.
    echo Luego ejecuta:
    echo   git commit -m "Fix: Reagregar archivos de web correctamente"
) else (
    echo [OK] Commit creado exitosamente.
)

echo.
echo ========================================
echo PASO 5: Verificando remoto
echo ========================================
echo.

git remote -v >nul 2>&1
if errorlevel 1 (
    echo Configurando repositorio remoto...
    git remote add origin https://github.com/luischanferoni/bioenlace.git
    echo [OK] Remoto configurado.
) else (
    echo [OK] Remoto ya configurado.
    git remote -v
)

echo.
echo ========================================
echo RESUMEN
echo ========================================
echo.
echo Estado actual:
git status --short | find /c /v "" > temp_total.txt 2>nul
set /p total=<temp_total.txt 2>nul
del temp_total.txt 2>nul
if "%total%"=="" set total=0
echo Archivos en staging: %total%

echo.
echo Para subir los cambios al repositorio:
echo   git push origin main
echo.
echo Si necesitas forzar (sobrescribir lo que hay en GitHub):
echo   git push -u origin main --force
echo.
echo ========================================
pause

