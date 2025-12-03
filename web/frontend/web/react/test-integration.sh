#!/bin/bash

echo "🚀 Iniciando prueba de integración React + API..."

# Función para verificar si un puerto está en uso
check_port() {
    if lsof -Pi :$1 -sTCP:LISTEN -t >/dev/null ; then
        echo "❌ Puerto $1 está en uso"
        return 1
    else
        echo "✅ Puerto $1 está disponible"
        return 0
    fi
}

# Verificar puertos
echo "🔍 Verificando puertos..."
check_port 3000
check_port 8080

# Crear archivo .env para React
echo "⚙️ Configurando variables de entorno..."
cat > .env << 'EOF'
VITE_API_BASE_URL=http://localhost:8080/api
VITE_APP_NAME=VitaMind
VITE_APP_VERSION=1.0.0
EOF

echo "✅ Archivo .env creado"

# Función para limpiar procesos al salir
cleanup() {
    echo ""
    echo "🧹 Limpiando procesos..."
    pkill -f "node test-api.js" 2>/dev/null || true
    pkill -f "npm run dev" 2>/dev/null || true
    echo "✅ Procesos limpiados"
    exit 0
}

# Capturar Ctrl+C
trap cleanup SIGINT

echo ""
echo "🎯 Iniciando servidor de prueba API..."
node test-api.js &
API_PID=$!

# Esperar un momento para que el servidor inicie
sleep 2

echo ""
echo "🎯 Iniciando servidor de desarrollo React..."
npm run dev &
REACT_PID=$!

echo ""
echo "🎉 ¡Servidores iniciados!"
echo ""
echo "📱 URLs disponibles:"
echo "  React App: http://localhost:3000"
echo "  API Backend: http://localhost:8080/api"
echo ""
echo "🔑 Credenciales de prueba:"
echo "  Email: juan@test.com"
echo "  Password: password"
echo ""
echo "📋 Para probar:"
echo "1. Abre http://localhost:3000 en tu navegador"
echo "2. Haz login con las credenciales de prueba"
echo "3. Navega por las diferentes secciones"
echo "4. Prueba el chat en una consulta"
echo ""
echo "Presiona Ctrl+C para detener ambos servidores"

# Esperar a que el usuario presione Ctrl+C
wait
