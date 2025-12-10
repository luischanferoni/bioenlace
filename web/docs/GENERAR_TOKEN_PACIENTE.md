# Generar Token de Prueba para Paciente

Este documento explica cómo generar un token de autenticación para probar la app móvil como paciente.

## Opción 1: Usar el Script PHP (Recomendado)

### Desde línea de comandos:
```bash
cd web
php generar_token_paciente.php 29486884
```

### Desde el navegador:
```
http://localhost/bioenlace/web/generar_token_paciente.php?dni=29486884
```

El script mostrará:
- Información del paciente
- Token JWT generado
- Instrucciones de uso

## Opción 2: Usar el Endpoint API

### Con cURL:
```bash
curl -X POST "http://localhost/bioenlace/api/v1/auth/generate-test-token" \
  -H "Content-Type: application/json" \
  -d '{"dni": "29486884"}'
```

### Con GET (más simple):
```bash
curl "http://localhost/bioenlace/api/v1/auth/generate-test-token?dni=29486884"
```

### Respuesta JSON:
```json
{
  "success": true,
  "message": "Token generado exitosamente para paciente con DNI: 29486884",
  "data": {
    "user": {
      "id": 5748,
      "name": "nombre_usuario",
      "email": "email@ejemplo.com",
      "role": "paciente",
      "permissions": []
    },
    "persona": {
      "id_persona": 920778,
      "nombre": "LUIS",
      "apellido": "CHANFERONI",
      "documento": "29486884"
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }
}
```

## Usar el Token en la App Móvil

### Opción A: Guardar en SharedPreferences (Flutter)

1. Ejecuta el script o endpoint para obtener el token
2. Copia el token de la respuesta
3. En la app Flutter, guarda el token:

```dart
final prefs = await SharedPreferences.getInstance();
await prefs.setString('auth_token', 'TU_TOKEN_AQUI');
await prefs.setString('user_id', '5748'); // ID del usuario
await prefs.setString('user_name', 'LUIS GUSTAVO CHANFERONI');
await prefs.setBool('is_logged_in', true);
```

### Opción B: Usar en peticiones HTTP

Agrega el header `Authorization` en todas las peticiones:

```dart
final headers = {
  'Content-Type': 'application/json',
  'Authorization': 'Bearer TU_TOKEN_AQUI',
};
```

## Paciente de Prueba

- **DNI**: 29486884
- **Nombre**: LUIS GUSTAVO CHANFERONI
- **ID Persona**: 920778
- **ID Usuario**: 5748

## Notas Importantes

⚠️ **Este endpoint es solo para desarrollo/pruebas**. No debe estar habilitado en producción.

El token tiene una validez de **24 horas**. Después de ese tiempo, necesitarás generar uno nuevo.

Si el paciente no tiene usuario asociado (`id_user` es NULL), necesitarás crear un usuario primero en el sistema.

