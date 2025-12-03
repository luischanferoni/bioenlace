# 🔧 Configuración para API Real de Yii2

## ✅ Configuración completada:

### 🔧 Backend (Yii2)
- ✅ Módulo API configurado en `backend/modules/api/`
- ✅ Controladores REST creados (Auth, Chat, Consultas, Personas)
- ✅ Rutas de API configuradas en `backend/config/main.php`
- ✅ Autenticación JWT implementada
- ✅ CORS habilitado
- ✅ Migraciones de base de datos preparadas

### 🎨 Frontend (React)
- ✅ Variables de entorno configuradas (`.env`)
- ✅ URL de API actualizada: `http://localhost/vitamind/VitaMind/api/v1`
- ✅ Proxy de Vite configurado
- ✅ Servicios de API actualizados
- ✅ Hooks personalizados creados

## 🚀 Para probar la integración:

### 1. Configurar Backend
```bash
# Ejecutar en el directorio backend
setup-real-api.bat
```

### 2. Probar API
```bash
# Ejecutar en el directorio frontend/web/react
test-api-real.bat
```

### 3. Iniciar React
```bash
# Ejecutar en el directorio frontend/web/react
test-real-api.bat
```

## 📱 URLs configuradas:
- **React App**: http://localhost:3000
- **API Backend**: http://localhost/vitamind/VitaMind/api/v1

## 🔗 Endpoints disponibles:

### Autenticación
- `POST /api/v1/auth/login` - Login
- `POST /api/v1/auth/register` - Registro
- `GET /api/v1/auth/me` - Usuario actual
- `POST /api/v1/auth/logout` - Logout
- `POST /api/v1/auth/refresh-token` - Refrescar token

### Chat
- `GET /api/v1/chat/messages/{id}` - Mensajes de consulta
- `POST /api/v1/chat/send` - Enviar mensaje
- `GET /api/v1/chat/status/{id}` - Estado del chat

### Consultas
- `GET /api/v1/consultas` - Lista consultas
- `GET /api/v1/consultas/{id}` - Ver consulta
- `POST /api/v1/consultas/create` - Crear consulta
- `PUT /api/v1/consultas/{id}/update` - Actualizar consulta
- `DELETE /api/v1/consultas/{id}/delete` - Eliminar consulta

### Personas
- `GET /api/v1/personas` - Lista personas
- `GET /api/v1/personas/{id}` - Ver persona
- `GET /api/v1/personas/{id}/timeline` - Timeline persona
- `POST /api/v1/personas/create` - Crear persona
- `PUT /api/v1/personas/{id}/update` - Actualizar persona
- `DELETE /api/v1/personas/{id}/delete` - Eliminar persona

## ⚙️ Configuración técnica:

### Variables de entorno (.env)
```
VITE_API_BASE_URL=http://localhost/vitamind/VitaMind/api/v1
VITE_APP_NAME=VitaMind
VITE_APP_VERSION=1.0.0
```

### Proxy de Vite
```javascript
proxy: {
  '/api': {
    target: 'http://localhost/vitamind/VitaMind',
    changeOrigin: true,
    secure: false,
    rewrite: (path) => path.replace(/^\/api/, '/api/v1')
  }
}
```

### Rutas de Yii2
```php
'api/v1/auth/login' => 'api/auth/login',
'api/v1/consultas' => 'api/consulta/index',
'api/v1/personas' => 'api/persona/index',
// ... más rutas
```

## 🧪 Pruebas de la API:

### Login
```bash
curl -X POST http://localhost/vitamind/VitaMind/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"password"}'
```

### Consultas
```bash
curl -X GET http://localhost/vitamind/VitaMind/api/v1/consultas \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Personas
```bash
curl -X GET http://localhost/vitamind/VitaMind/api/v1/personas \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## 🐛 Solución de problemas:

### Si la API no responde:
1. Verificar que el servidor web esté ejecutándose
2. Verificar que las rutas estén configuradas correctamente
3. Verificar que el módulo API esté habilitado

### Si hay errores de CORS:
1. Verificar que CORS esté habilitado en Yii2
2. Verificar que las cabeceras estén configuradas correctamente

### Si hay errores de autenticación:
1. Verificar que JWT esté configurado correctamente
2. Verificar que el token se esté enviando en las cabeceras

## 🎯 Próximos pasos:

1. **Configurar la API real** ejecutando `setup-real-api.bat`
2. **Probar los endpoints** ejecutando `test-api-real.bat`
3. **Iniciar React** ejecutando `test-real-api.bat`
4. **Probar la integración** en el navegador

¡La configuración está lista para usar la API real de Yii2!
