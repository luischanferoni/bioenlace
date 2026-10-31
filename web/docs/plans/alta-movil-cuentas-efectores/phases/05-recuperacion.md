# Fase 05 — Recuperación de acceso

## Objetivo

Recuperar accesos sin convertir e-mail o soporte en un factor de autenticación débil.

## Checklist

- [x] App: volver a verificar identidad y revocar credenciales/dispositivos anteriores.
- [x] Web-only: flujo asistido con prueba de identidad, revisión de riesgo y registro de auditoría.
- [x] Revocar sesiones activas, passkeys anteriores y desafíos pendientes antes de recuperar.
- [ ] Notificar a canales registrados y otros administradores, sin usarlos como autorización única.
- [x] Exigir enrolar una passkey nueva y presentar recuperación explícita al usuario.
- [ ] Definir escalamiento/hold para señales de fraude o disputas de propiedad de la organización.

## Criterio de salida

La recuperación completa deja evidencia auditable y no puede completarse únicamente con un e-mail no verificado.

## Estado de implementación

- Recuperación asistida web-only: `AccountRecoveryService` + solicitud pública (`/auth/recovery-request`), consumo de código de un solo uso (`/auth/recovery-consume`) y revisión/aprobación de superadmin (`/auth/recovery-review`, `/auth/recovery-approve`). Al aprobar se revocan sesiones y passkeys, se emite un código de un solo uso y se redirige al enrolamiento de passkey.
- La app recupera acceso re-verificando identidad con Didit (flujo `login-biometrico` ya implementado). Pendiente: notificaciones de auditoría y escalamiento/hold ante fraude.
