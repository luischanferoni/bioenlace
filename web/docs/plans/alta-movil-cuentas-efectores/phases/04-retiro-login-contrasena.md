# Retiro del login web con contraseña

## Objetivo

Retirar el login con contraseña como método de autenticación web, dejando solo passkeys y aprobación QR.

## Estado actual

- ✅ Passkeys implementados y funcionales
- ✅ Aprobación QR implementada
- ✅ Recuperación asistida web-only implementada
- ✅ Parámetro `passwordLoginWebEnabled = false` configurado por defecto
- ✅ Login móvil con contraseña ya retirado (`410 Gone`)

## Configuración

### Parámetro en `common/config/params.php`

```php
'passwordLoginWebEnabled' => false,  // false = solo passkey/QR, true = contraseñas habilitadas
```

### Sobrescribir en `common/config/params-local.php`

Solo para debugging o casos especiales:

```php
'passwordLoginWebEnabled' => true,  // Habilitar temporalmente contraseñas
```

## Comportamiento en AuthController

```php
public function actionLogin()
{
    $passwordLoginEnabled = (bool) (Yii::$app->params['passwordLoginWebEnabled'] ?? false);

    if ($passwordLoginEnabled
        && $model->load(Yii::$app->request->post())
        && $model->login()
    ) {
        // Login con contraseña (solo si está habilitado)
    }

    // Si passwordLoginEnabled = false, el formulario de contraseña no se muestra
    return $this->render('@frontend/views/login/login', [
        'model' => $model,
        'passwordLoginEnabled' => $passwordLoginEnabled,
        // ...
    ]);
}
```

## Vista de login

### Por defecto (`passwordLoginWebEnabled = false`)

- Formulario de usuario/contraseña OCULTO
- Botón destacado "Ingresar con passkey"
- Botón "Escanear QR desde la app"
- Link "¿No tenés passkey?" → recovery asistida

### Si se habilita temporalmente (`passwordLoginWebEnabled = true`)

- Formulario de usuario/contraseña visible
- Botón "Ingresar con passkey"
- Botón "Escanear QR desde la app"
- Link "¿Olvidaste tu contraseña?" → recovery asistida

## Recuperación de acceso

Con contraseñas deshabilitadas:

1. Usuario sin passkey → "¿No tenés passkey?"
2. Flujo de recuperación asistida (`/auth/recovery-request`)
3. Superadmin revisa y aprueba (`/auth/recovery-review`)
4. Usuario recibe código de un solo uso
5. Usuario consume código y debe enrolar passkey nueva

## Usuarios sin dispositivo compatible

Para usuarios que no pueden usar WebAuthn:

1. Solicitar recuperación asistida
2. Superadmin verifica identidad manualmente
3. Emitir código de recuperación
4. Usuario consume código y se le permite enrolar passkey
5. Si el dispositivo no soporta WebAuthn:
   - Evaluar dispositivo compatible (USB security key)
   - O mantener acceso temporal mediante `passwordLoginWebEnabled = true` en params-local
   - Documentar caso especial en runbook de soporte

## Rollback temporal

Si se necesita habilitar contraseñas temporalmente:

```php
// En common/config/params-local.php
'passwordLoginWebEnabled' => true,
```

Esto reactiva el formulario de contraseña inmediatamente.

## Limpieza pendiente

- [ ] Retirar código legacy de password recovery por email (AuthController actions)
- [ ] Retirar formularios de cambio de contraseña (AuthController actions)
- [ ] Retirar vistas legacy de password recovery
- [ ] Actualizar documentación de soporte

## Observaciones

- El retiro de contraseñas es reversible mediante parámetro de configuración
- No requiere migración de datos ni cambios de esquema
- La recuperación asistida es el fallback para usuarios sin passkey
- Los roles AdminEfector y Administrativo pueden seguir usando passkey web
- El login móvil con contraseña ya fue retirado (`410 Gone`)
- No hay fase de transición: el cambio es directo mediante parámetro
