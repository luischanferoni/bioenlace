#!/bin/bash

# Script de integración React con API Yii2

echo "🚀 Integrando React con API de Yii2..."

# Verificar si Node.js está instalado
if ! command -v node &> /dev/null; then
    echo "❌ Node.js no está instalado. Por favor instala Node.js desde https://nodejs.org/"
    exit 1
fi

echo "✅ Node.js detectado"

# Verificar si npm está instalado
if ! command -v npm &> /dev/null; then
    echo "❌ npm no está instalado. Por favor instala npm"
    exit 1
fi

echo "✅ npm detectado"

# Instalar dependencias adicionales
echo "📦 Instalando dependencias adicionales..."
npm install react-hot-toast date-fns

if [ $? -eq 0 ]; then
    echo "✅ Dependencias adicionales instaladas"
else
    echo "❌ Error instalando dependencias adicionales"
    exit 1
fi

# Crear archivo de configuración de entorno
echo "⚙️ Creando archivo de configuración..."
cat > .env << 'EOF'
VITE_API_BASE_URL=http://localhost:8080/api
VITE_APP_NAME=VitaMind
VITE_APP_VERSION=1.0.0
EOF

echo "✅ Archivo .env creado"

# Crear script de desarrollo
echo "📝 Creando script de desarrollo..."
cat > dev.sh << 'EOF'
#!/bin/bash
echo "🚀 Iniciando servidor de desarrollo..."
echo "📱 React: http://localhost:3000"
echo "🔗 API: http://localhost:8080/api"
echo ""
echo "Presiona Ctrl+C para detener"
npm run dev
EOF

chmod +x dev.sh

echo "✅ Script de desarrollo creado"

# Crear script de build
echo "📝 Creando script de build..."
cat > build.sh << 'EOF'
#!/bin/bash
echo "🏗️ Construyendo aplicación React..."
npm run build

if [ $? -eq 0 ]; then
    echo "✅ Build completado exitosamente"
    echo "📁 Archivos generados en: dist/"
    echo "🔗 Para servir: npx serve dist"
else
    echo "❌ Error en el build"
    exit 1
fi
EOF

chmod +x build.sh

echo "✅ Script de build creado"

# Crear README de integración
echo "📝 Creando README de integración..."
cat > INTEGRATION.md << 'EOF'
# 🚀 Integración React con API Yii2

## 📋 Configuración Completada

### ✅ Backend (Yii2 API)
- [x] Módulo API configurado
- [x] Controladores REST creados
- [x] Autenticación JWT implementada
- [x] CORS habilitado
- [x] Migraciones de base de datos

### ✅ Frontend (React SPA)
- [x] Hooks personalizados creados
- [x] Servicios de API configurados
- [x] Componentes mejorados
- [x] Autenticación integrada
- [x] Chat en tiempo real

## 🚀 Cómo usar

### 1. Configurar Backend
```bash
cd backend
chmod +x setup-api.sh
./setup-api.sh
```

### 2. Configurar Frontend
```bash
cd frontend/web/react
chmod +x integration.sh
./integration.sh
```

### 3. Ejecutar en desarrollo
```bash
# Terminal 1: Backend
cd backend
php yii serve --port=8080

# Terminal 2: Frontend
cd frontend/web/react
./dev.sh
```

### 4. Construir para producción
```bash
cd frontend/web/react
./build.sh
```

## 🔗 URLs
- **React App**: http://localhost:3000
- **API Backend**: http://localhost:8080/api
- **Documentación API**: http://localhost:8080/api/docs

## 📱 Endpoints disponibles

### Autenticación
- `POST /api/auth/login` - Login
- `POST /api/auth/register` - Registro
- `GET /api/auth/me` - Usuario actual
- `POST /api/auth/logout` - Logout

### Chat
- `GET /api/chat/messages/{id}` - Mensajes
- `POST /api/chat/send` - Enviar mensaje
- `GET /api/chat/status/{id}` - Estado del chat

### Consultas
- `GET /api/consultas` - Lista consultas
- `GET /api/consultas/{id}` - Ver consulta
- `POST /api/consultas` - Crear consulta
- `PUT /api/consultas/{id}` - Actualizar consulta
- `DELETE /api/consultas/{id}` - Eliminar consulta

### Personas
- `GET /api/personas` - Lista personas
- `GET /api/personas/{id}` - Ver persona
- `GET /api/personas/{id}/timeline` - Timeline persona
- `POST /api/personas` - Crear persona
- `PUT /api/personas/{id}` - Actualizar persona
- `DELETE /api/personas/{id}` - Eliminar persona

## 🔧 Configuración

### Variables de entorno
```env
VITE_API_BASE_URL=http://localhost:8080/api
VITE_APP_NAME=VitaMind
VITE_APP_VERSION=1.0.0
```

### Base de datos
- Tabla `chat_messages` creada
- Índices y claves foráneas configuradas
- Migraciones ejecutadas

## 🎯 Próximos pasos
1. Probar endpoints de la API
2. Configurar autenticación
3. Implementar funcionalidades específicas
4. Optimizar rendimiento
5. Agregar tests
EOF

echo "✅ README de integración creado"

echo ""
echo "🎉 ¡Integración completada!"
echo ""
echo "📋 Para continuar:"
echo "1. Configurar backend: cd backend && ./setup-api.sh"
echo "2. Ejecutar desarrollo: ./dev.sh"
echo "3. Construir producción: ./build.sh"
echo ""
echo "🔗 URLs:"
echo "  React: http://localhost:3000"
echo "  API: http://localhost:8080/api"
echo ""
echo "📚 Documentación: INTEGRATION.md"
