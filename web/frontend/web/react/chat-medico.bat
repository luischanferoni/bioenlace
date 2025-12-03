@echo off
echo 🏥 VitaMind - Chat Médico
echo =========================

echo.
echo 🔧 Configurando entorno...
echo VITE_API_BASE_URL=http://localhost/vitamind/VitaMind/api/v1 > .env
echo VITE_APP_NAME=VitaMind >> .env
echo VITE_APP_VERSION=1.0.0 >> .env
echo ✅ Variables de entorno configuradas

echo.
echo 🧪 Probando endpoints de chat médico...

echo.
echo 📋 1. Probando GET /api/v1/consulta-chat/messages/1...
curl -X GET http://localhost/vitamind/VitaMind/api/v1/consulta-chat/messages/1 ^
  --silent --show-error

echo.
echo.
echo 📋 2. Probando POST /api/v1/consulta-chat/send...
curl -X POST http://localhost/vitamind/VitaMind/api/v1/consulta-chat/send ^
  -H "Content-Type: application/json" ^
  -d "{\"consulta_id\":1,\"message\":\"Hola desde el chat médico\",\"user_id\":1,\"user_role\":\"medico\"}" ^
  --silent --show-error

echo.
echo.
echo 📋 3. Probando GET /api/v1/consulta-chat/status/1...
curl -X GET http://localhost/vitamind/VitaMind/api/v1/consulta-chat/status/1 ^
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
echo 🔑 Funcionalidades del chat médico:
echo   ✅ Carga mensajes de consultas médicas
echo   ✅ Envía mensajes con roles específicos
echo   ✅ Control de mensajes leídos/no leídos
echo   ✅ Separado del bot de turnos
echo   ✅ Tabla: consulta_chat_messages
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
