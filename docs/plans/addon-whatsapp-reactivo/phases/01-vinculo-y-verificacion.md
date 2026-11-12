# Fase 01 — Vínculo y verificación inicial

## Objetivo

Reemplazar el vínculo por match de teléfono + “SI” por una verificación fuerte que asocie `wa_id` con `user_id`/`id_persona`.

## Estado de la primera entrega

Implementado:

- Migración `m261112_120000_whatsapp_addon_identity`: estados nuevos, columnas de verificación y token, tablas de verificación y acción pendiente.
- `AsistenteWhatsappVinculo` ampliado y `WhatsAppLinkToken` (hash + expiración).
- `AsistenteWhatsappVerificacion` y `AsistenteWhatsappAccionPendiente`.
- `WhatsAppVerificationService` con emisión de enlace y vinculación por Didit KYC contra una Persona existente.
- Página web `whatsapp-verificacion/<token>` y rutas.
- `WhatsAppIdentityService` ya no vincula por teléfono ni por “SI”: emite enlace de verificación.

Pendiente:

- Métodos “app” y “passkey” en la página.
- Pruebas con base de datos (el entorno de esta sesión no tiene MySQL disponible).
- Expiración automática de vínculos inactivos.

## Tareas

- [x] Migración nueva para ampliar `asistente_whatsapp_vinculo`: estados, `verified_at`, `verification_method`, `verification_expires_at`, `link_token_hash`, `link_token_expires_at`, `failed_attempts`, `consent_at`, `consent_version`, `opt_out_at`.
- [x] Migración nueva para `asistente_whatsapp_verificacion` (auditoría de intentos y resultados).
- [x] Actualizar `AsistenteWhatsappVinculo` con los nuevos campos, constantes de estado y helpers de token.
- [x] Retirar `ESTADO_PENDIENTE_CONFIRMACION` y el flujo de “SI” de `WhatsAppIdentityService::resolveOrBootstrap`; conservar `establishYiiIdentity` para la sesión.
- [x] Quitar `findUniquePatientByPhone` como criterio de vinculación; si se conserva, usarlo solo como pista de prellenado, nunca como prueba.
- [x] Crear el servicio de vinculación: genera token opaco, arma la URL, resuelve el método y confirma la identidad.
- [x] Crear la página web de verificación en el origen de Bioenlace. No muestra PII antes de verificar.
- [ ] Implementar el método “app”: deep link a la app para huella local y retorno con token.
- [ ] Implementar el método “passkey”: reutilizar `PasskeyAuthenticationService` con propósito `whatsapp-stepup`.
- [x] Implementar el método “Didit” de vinculación: `DiditClient::createVerificationSession` + `getIdentityVerification`; comparar `documento` con la Persona. El step-up biométrico (`getBiometricAuth`) queda para la fase 02.
- [x] Reglas de rechazo: sin cuenta, identidad que no coincide, número ya vinculado a otra Persona, demasiados intentos.
- [ ] Expiración de vínculos inactivos y bloqueo temporal (el bloqueo por intentos ya está).
- [x] Textos en catálogo de copy del canal, no en el servicio.
- [ ] Tests: vínculo exitoso por cada método, mismatch de identidad, token vencido, token reutilizado, número ya vinculado, bloqueo por intentos.

## Criterio de salida

Un `wa_id` solo pasa a `ACTIVO` con verificación aprobada y coincidencia con la Persona; el flujo “SI” ya no existe; los intentos quedan auditados; ningún mensaje revela nombre o datos antes de verificar.
