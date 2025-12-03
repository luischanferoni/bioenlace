@echo off
echo ========================================
echo Agregando archivos de web al repositorio
echo ========================================
echo.

cd /d "%~dp0"

echo Verificando que estamos en el directorio correcto...
if not exist web (
    echo ERROR: No se encuentra la carpeta web.
    echo Asegurate de ejecutar este script desde d:\codigo\bioenlace\
    pause
    exit /b 1
)

echo.
echo [PASO 1] Asegurando que no existe .git en web...
if exist web\.git (
    echo Eliminando .git de web...
    rmdir /s /q web\.git
    echo .git eliminado de web.
) else (
    echo [OK] No existe .git en web.
)

echo.
echo [PASO 2] Verificando repositorio Git en el directorio raiz...
if not exist .git (
    echo Inicializando repositorio Git...
    git init
    git branch -M main
)

echo.
echo [PASO 3] Agregando todos los archivos (incluyendo web)...
git add .
if errorlevel 1 (
    echo ERROR: No se pudieron agregar los archivos.
    pause
    exit /b 1
)

echo.
echo [PASO 4] Verificando archivos agregados...
git status --short | findstr /C:"web" | findstr /V "^.git"
if errorlevel 1 (
    echo ADVERTENCIA: No se encontraron archivos de web en el staging area.
) else (
    echo [OK] Archivos de web detectados.
)

echo.
echo [PASO 5] Creando commit...
git commit -m "Fix: Agregar archivos de web correctamente"
if errorlevel 1 (
    echo ADVERTENCIA: No se pudo crear el commit. Puede que no haya cambios.
    echo Esto es normal si los archivos ya estaban commiteados.
)

echo.
echo [PASO 6] Verificando conexion con repositorio remoto...
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
echo Si quieres subir los cambios, ejecuta:
echo   git push origin main
echo.
echo O si necesitas forzar la subida:
echo   git push -u origin main --force
echo.
pause

