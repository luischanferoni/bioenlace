# Fase 05 — Recuperación de acceso

## Objetivo

Recuperar accesos sin convertir e-mail o soporte en un factor de autenticación débil.

## Checklist

- [x] App: volver a verificar identidad y revocar credenciales/dispositivos anteriores.
- [x] Web-only: flujo asistido con prueba de identidad, revisión de riesgo y registro de auditoría.
- [x] Revocar sesiones activas, passkeys anteriores y desafíos pendientes antes de recuperar.
- [x] Notificar a canales registrados y otros administradores, sin usarlos como autorización única.
- [x] Crear catálogo de notificaciones (`AccountRecoveryNotificationCatalog`) con copy para cada evento.
- [x] Implementar envío de notificaciones al aprobar, consumir y rechazar solicitudes.
- [x] Implementar acción de rechazo (`AccountRecoveryService::reject`) en AuthController.
- [x] Implementar detección de señales de fraude (`checkFraudSignals`) con estructura básica.
- [x] Exigir enrolar una passkey nueva y presentar recuperación explícita al usuario.
- [ ] Completar implementación de notificaciones (canal real: email/push/in-app, cola de envío).
- [ ] Completar lógica de detección de fraude (umbrales, tabla de holds, notificación a ops).

## Criterio de salida

La recuperación completa deja evidencia auditable y no puede completarse únicamente con un e-mail no verificado.

## Estado de implementación

- Recuperación asistida web-only: `AccountRecoveryService` + solicitud pública (`/auth/recovery-request`), consumo de código de un solo uso (`/auth/recovery-consume`) y revisión/aprobación de superadmin (`/auth/recovery-review`, `/auth/recovery-approve`). Al aprobar se revocan sesiones y passkeys, se emite un código de un solo uso y se redirige al enrolamiento de passkey.
- La app recupera acceso re-verificando identidad con Didit (flujo `login-biometrico` ya implementado). Pendiente: notificaciones de auditoría y escalamiento/hold ante fraude.
