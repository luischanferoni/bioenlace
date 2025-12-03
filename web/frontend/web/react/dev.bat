@echo off
echo 🚀 VitaMind - Desarrollo
echo =======================

echo.
echo 🔧 Configurando entorno de desarrollo...
echo VITE_API_BASE_URL=http://localhost/vitamind/VitaMind/api/v1 > .env
echo VITE_APP_NAME=VitaMind >> .env
echo VITE_APP_VERSION=1.0.0 >> .env
echo ✅ Variables de entorno configuradas

echo.
echo 🧪 Probando conectividad con la API...

echo.
echo 📋 Probando endpoint de mensajes...
curl -X GET http://localhost/vitamind/VitaMind/api/v1/consulta-chat/messages/1 ^
  --silent --show-error

echo.
echo.
echo 🎯 Iniciando servidor de desarrollo React...
start "React Dev Server" cmd /k "npm run dev"

echo.
echo 🎉 ¡Servidor React iniciado!
echo.
echo 📱 URLs disponibles:
echo   React App: http://localhost:3000
echo   API Backend: http://localhost/vitamind/VitaMind/api/v1
echo.
echo 🔑 Funcionalidades disponibles:
echo   ✅ Chat médico en consultas
echo   ✅ Gestión de personas
echo   ✅ Consultas unificadas
echo   ✅ Autenticación JWT
echo.
echo 📋 Para probar:
echo 1. Asegúrate de que tu servidor web esté ejecutándose
echo 2. Abre http://localhost:3000 en tu navegador
echo 3. El chat debería cargar mensajes de la nueva tabla
echo 4. Puedes enviar mensajes que se guardarán en consulta_chat_messages
echo.
echo El servidor React se ejecuta en una ventana separada.
echo Cierra la ventana para detener el servidor.
echo.
pause
