@echo off
echo ========================================
echo Script para limpiar y reinicializar Git
echo ========================================
echo.
echo Este script:
echo 1. Eliminara todo el historial de Git existente
echo 2. Creara un nuevo repositorio limpio
echo 3. Subira todos los archivos al repositorio remoto
echo.
pause

echo.
echo [PASO 1/5] Eliminando directorio .git existente...
if exist .git (
    rmdir /s /q .git
    echo Directorio .git eliminado correctamente.
) else (
    echo No existe directorio .git.
)

echo.
echo [PASO 2/5] Inicializando nuevo repositorio Git...
git init
if errorlevel 1 (
    echo ERROR: No se pudo inicializar Git. Verifica que Git este instalado.
    pause
    exit /b 1
)

echo.
echo [PASO 3/5] Agregando todos los archivos...
git add .
if errorlevel 1 (
    echo ERROR: No se pudieron agregar los archivos.
    pause
    exit /b 1
)

echo.
echo [PASO 4/5] Creando commit inicial...
git commit -m "Initial commit"
if errorlevel 1 (
    echo ERROR: No se pudo crear el commit. Verifica que haya archivos para commitear.
    pause
    exit /b 1
)

echo.
echo [PASO 5/5] Configurando y subiendo al repositorio remoto...
git remote remove origin 2>nul
git remote add origin https://github.com/luischanferoni/bioenlace.git
git branch -M main
git push -u origin main --force
if errorlevel 1 (
    echo ERROR: No se pudo subir al repositorio remoto.
    echo Verifica tus credenciales de GitHub.
    pause
    exit /b 1
)

echo.
echo ========================================
echo ¡Proceso completado exitosamente!
echo ========================================
echo.
echo El repositorio ha sido limpiado, reinicializado y subido correctamente.
echo Puedes verificar en: https://github.com/luischanferoni/bioenlace
echo.
pause

