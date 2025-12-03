@echo off
echo ========================================
echo Verificando estructura del repositorio
echo ========================================
echo.

cd /d "%~dp0"

echo Verificando si existe .git en el directorio raiz...
if exist .git (
    echo [OK] Existe .git en el directorio raiz.
) else (
    echo [ERROR] No existe .git en el directorio raiz.
    echo Inicializando repositorio...
    git init
)

echo.
echo Verificando si existe .git en web...
if exist web\.git (
    echo [ADVERTENCIA] Existe .git en web! Esto puede causar problemas.
    echo Eliminando .git de web...
    rmdir /s /q web\.git
    echo .git de web eliminado.
) else (
    echo [OK] No existe .git en web.
)

echo.
echo Verificando archivos en el repositorio...
git status --short
if errorlevel 1 (
    echo No hay cambios pendientes o el repositorio no esta inicializado correctamente.
)

echo.
echo Verificando estructura de carpetas...
if exist web (
    echo [OK] Carpeta web existe.
) else (
    echo [ERROR] Carpeta web no existe!
)

if exist mobile (
    echo [OK] Carpeta mobile existe.
) else (
    echo [ERROR] Carpeta mobile no existe!
)

echo.
echo ========================================
echo Verificacion completada
echo ========================================
echo.
echo Si todo esta correcto, puedes ejecutar:
echo   git add .
echo   git commit -m "Fix: Corregir estructura del repositorio"
echo   git push origin main
echo.
pause

