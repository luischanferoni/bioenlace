# 🚀 Prueba de Integración React + API Yii2

## ✅ Lo que hemos configurado:

### 🔧 Backend (API)
- ✅ Módulo API de Yii2 configurado
- ✅ Controladores REST creados (Auth, Chat, Consultas, Personas)
- ✅ Autenticación JWT implementada
- ✅ CORS habilitado
- ✅ Migraciones de base de datos preparadas

### 🎨 Frontend (React SPA)
- ✅ Hooks personalizados creados (`useChat`, `useConsultas`, `usePersonas`)
- ✅ Servicios de API configurados
- ✅ Componentes mejorados (Chat, Consultas, Personas)
- ✅ Autenticación integrada
- ✅ Chat en tiempo real con polling

## 🚀 Cómo probar la integración:

### Opción 1: Script automático (Recomendado)
```bash
# Ejecutar el script de prueba
start-test.bat
```

### Opción 2: Manual
```bash
# Terminal 1: Servidor API de prueba
node test-api.js

# Terminal 2: Servidor React
npm run dev
```

## 📱 URLs disponibles:
- **React App**: http://localhost:3000
- **API Backend**: http://localhost:8080/api

## 🔑 Credenciales de prueba:
- **Email**: juan@test.com
- **Password**: password

## 📋 Funcionalidades a probar:

### 1. 🔐 Autenticación
- [ ] Login con credenciales de prueba
- [ ] Logout
- [ ] Persistencia de sesión

### 2. 📋 Consultas
- [ ] Lista de consultas
- [ ] Filtros por estado y fecha
- [ ] Paginación
- [ ] Crear nueva consulta
- [ ] Editar consulta
- [ ] Eliminar consulta

### 3. 👥 Personas
- [ ] Lista de personas
- [ ] Búsqueda por nombre/documento
- [ ] Paginación
- [ ] Ver detalles de persona
- [ ] Timeline de persona
- [ ] Crear nueva persona
- [ ] Editar persona
- [ ] Eliminar persona

### 4. 💬 Chat
- [ ] Cargar mensajes de consulta
- [ ] Enviar mensaje (solo médicos)
- [ ] Polling automático para nuevos mensajes
- [ ] Indicador de conexión
- [ ] Formateo de fechas

## 🎯 Endpoints de prueba disponibles:

### Autenticación
- `POST /api/auth/login` - Login
- `GET /api/auth/me` - Usuario actual

### Chat
- `GET /api/chat/messages/{id}` - Mensajes de consulta
- `POST /api/chat/send` - Enviar mensaje

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

## 🐛 Solución de problemas:

### Si el puerto 3000 está ocupado:
```bash
# Cambiar puerto en vite.config.js
server: {
  port: 3001
}
```

### Si el puerto 8080 está ocupado:
```bash
# Cambiar puerto en test-api.js
const PORT = 8081;
```

### Si hay errores de CORS:
- Verificar que el servidor API esté ejecutándose
- Verificar la URL en .env

### Si hay errores de autenticación:
- Verificar que el token se esté enviando correctamente
- Verificar que el usuario esté logueado

## 📊 Datos de prueba incluidos:

### Usuarios
- Dr. Juan Pérez (medico) - juan@test.com
- María García (paciente) - maria@test.com

### Consultas
- Consulta #1: María García - Dolor de cabeza
- Consulta #2: Carlos López - Fiebre

### Personas
- María García - Documento: 12345678
- Carlos López - Documento: 87654321

### Mensajes de chat
- Mensajes de ejemplo en Consulta #1

## 🎉 ¡Listo para probar!

Ejecuta `start-test.bat` y abre http://localhost:3000 en tu navegador para comenzar la prueba.
