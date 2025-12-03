#!/bin/bash

# Script para construir y desplegar React en Yii2

echo "🚀 Construyendo React para VitaMind..."

# Navegar al directorio de React
cd "$(dirname "$0")"

# Instalar dependencias si no existen
if [ ! -d "node_modules" ]; then
    echo "📦 Instalando dependencias..."
    npm install
fi

# Construir para producción
echo "🔨 Construyendo para producción..."
npm run build

# Verificar que se creó el build
if [ -f "../dist/assets/main.js" ]; then
    echo "✅ Build completado exitosamente!"
    echo "📁 Archivos generados en: ../dist/"
    echo "🔗 Para desarrollo: npm run dev"
    echo "🌐 Para producción: Los archivos están listos en ../dist/"
else
    echo "❌ Error en el build"
    exit 1
fi
