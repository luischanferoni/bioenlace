# Migración de usuarios — entorno de demo

## Contexto

Dado que el entorno actual es principalmente de demo con pocos usuarios reales, la migración de usuarios se simplifica significativamente comparado con un entorno de producción con datos históricos críticos.

## Suposiciones

- La mayoría de los usuarios son datos de demo/prueba
- No hay datos clínicos reales que requieran preservación estricta
- No hay relaciones comerciales complejas (licencias activas, facturación histórica)
- Los pocos usuarios reales pueden reprocesar su enrolamiento de passkey si es necesario

## Estrategia de migración

### 1. Preservación de datos (sin transformación)

Las tablas existentes se preservan sin alteración de esquema:

- `user` - usuarios/identidades
- `persona` - datos de personas
- `profesional_efector_servicio` (PES) - asignaciones a efectores
- `efectores` - centros/servicios
- `licencia` - licencias/entitlements
- `billing_account` - cuentas de facturación (si existen)
- `auth_*` - tablas de autenticación (passkeys, sesiones, etc.)

No se requiere migración de datos ni transformación de columnas.

### 2. Periodo de transición

**Fase 1 - Coexistencia (actual):**
- Login con contraseña sigue disponible
- Passkeys son opcionales
- Usuarios existentes pueden enrolar passkey cuando quieran

**Fase 2 - Enrolamiento forzado (cuando se esté listo):**
- Comunicar a usuarios existentes que deben enrolar passkey
- Mostrar banner/notificación en web solicitando enrolamiento
- Usuarios sin passkey pueden seguir usando contraseña temporalmente

**Fase 3 - Corte definitivo:**
- Retirar login con contraseña
- Todos los usuarios deben usar passkey o QR
- Usuarios sin dispositivo compatible → recuperación asistida

### 3. Migración móvil (ya implementada)

La migración de JWT móviles desde `SharedPreferences` a almacenamiento seguro ya está implementada:

- ✅ JWT almacenados en Keychain (iOS) / EncryptedSharedPreferences (Android)
- ✅ Backups automáticos de Android deshabilitados
- ✅ Revocación server-side de sesiones (jti persistido)

### 4. Pasos para completar la migración

1. **Inventario rápido (opcional):**
   ```sql
   SELECT COUNT(*) FROM user;
   SELECT COUNT(*) FROM profesional_efector_servicio WHERE activo = 1;
   ```
   - Si hay pocos usuarios, no hace falta inventario detallado
   - Si hay más de lo esperado, revisar manualmente

2. **Activar enrolamiento de passkey:**
   - Ya existe el servicio `PasskeyAuthenticationService`
   - Ya existe la pantalla `/auth/passkeys`
   - Solo falta comunicación a usuarios

3. **Retirar login con contraseña:**
   - Configurar `passwordLoginWebEnabled = false` en params
   - Verificar que todos los usuarios relevantes tengan passkey enrolado
   - Tener disponible el flujo de recuperación asistida

4. **Documentación:**
   - Registrar fecha de corte
   - Documentar usuarios que necesitaron recuperación asistida
   - Guardar script de rollback (por si acaso)

## Script de rollback (por si acaso)

Si algo sale mal después de retirar contraseñas:

```php
// En params-local.php
'passwordLoginWebEnabled' => true,
```

Esto reactiva el login con contraseña inmediatamente sin necesidad de migración inversa.

## Conclusiones

Dado el contexto de demo, la migración es principalmente:
- Preservar tablas existentes (no se borra nada)
- Activar enrolamiento de passkey (solo cambio de configuración)
- Retirar contraseña cuando esté listo (solo cambio de configuración)

No se requiere:
- Migración de datos compleja
- Transformación de esquema
- Script de migración ETL
- Validación de integridad masiva

La única acción manual necesaria es comunicar a los pocos usuarios reales que deben enrolar passkey antes del corte.
