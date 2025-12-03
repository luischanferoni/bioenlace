#!/bin/bash

# Script de configuración para VitaMind React SPA

echo "🚀 Configurando VitaMind React SPA..."

# Verificar si Node.js está instalado
if ! command -v node &> /dev/null; then
    echo "❌ Node.js no está instalado. Por favor instala Node.js 18+ desde https://nodejs.org/"
    exit 1
fi

# Verificar versión de Node.js
NODE_VERSION=$(node -v | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 18 ]; then
    echo "❌ Se requiere Node.js 18 o superior. Versión actual: $(node -v)"
    exit 1
fi

echo "✅ Node.js $(node -v) detectado"

# Instalar dependencias
echo "📦 Instalando dependencias..."
npm install

if [ $? -eq 0 ]; then
    echo "✅ Dependencias instaladas correctamente"
else
    echo "❌ Error instalando dependencias"
    exit 1
fi

# Crear archivo de variables de entorno
echo "⚙️ Configurando variables de entorno..."
cat > .env.local << EOF
# API Configuration
VITE_API_URL=http://localhost:8080/api

# Development
VITE_APP_TITLE=VitaMind
VITE_APP_VERSION=1.0.0
EOF

echo "✅ Archivo .env.local creado"

# Crear directorio de build si no existe
mkdir -p ../dist

echo ""
echo "🎉 ¡Configuración completada!"
echo ""
echo "📋 Comandos disponibles:"
echo "  npm run dev     - Servidor de desarrollo (http://localhost:3000)"
echo "  npm run build   - Construir para producción"
echo "  npm run preview - Vista previa del build"
echo "  npm run lint    - Verificar código"
echo ""
echo "🔗 URLs importantes:"
echo "  Desarrollo: http://localhost:3000"
echo "  API: http://localhost:8080/api"
echo ""
echo "🚀 Para comenzar: npm run dev"
