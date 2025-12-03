@echo off
echo 🏗️ VitaMind - Build
echo ===================

echo.
echo 🔧 Configurando entorno de producción...
echo VITE_API_BASE_URL=http://localhost/vitamind/VitaMind/api/v1 > .env
echo VITE_APP_NAME=VitaMind >> .env
echo VITE_APP_VERSION=1.0.0 >> .env
echo ✅ Variables de entorno configuradas

echo.
echo 🏗️ Construyendo aplicación React...
npm run build

echo.
echo ✅ Build completado
echo.
echo 📁 Archivos generados en: dist/
echo 📱 Para usar en producción:
echo 1. Copia los archivos de dist/ a tu servidor web
echo 2. Configura las rutas para servir los archivos estáticos
echo 3. Asegúrate de que la API esté disponible
echo.
pause
