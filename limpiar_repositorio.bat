@echo off
echo ========================================
echo Script para limpiar el repositorio Git
echo ========================================
echo.
echo ADVERTENCIA: Este script eliminara todo el historial de Git
echo y cualquier conexion con el repositorio remoto.
echo.
pause

echo.
echo Eliminando directorio .git...
if exist .git (
    rmdir /s /q .git
    echo Directorio .git eliminado correctamente.
) else (
    echo No existe directorio .git.
)

echo.
echo Limpiando referencias de Git...
if exist .gitattributes del /f /q .gitattributes 2>nul
if exist .gitmodules del /f /q .gitmodules 2>nul

echo.
echo ========================================
echo Limpieza completada!
echo ========================================
echo.
echo Ahora puedes ejecutar reiniciar_git.bat para crear un nuevo repositorio limpio.
echo.
pause

