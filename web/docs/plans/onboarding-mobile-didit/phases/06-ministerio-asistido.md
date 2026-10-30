# Fase 6 — Ministerio asistido por admin + token link

## Objetivo

Reemplazar el self-service de ministerio por un alta asistida con verificación de identidad y sin pago.

## Entregables

- Admin crea la solicitud (organización + persona de contacto) → `PENDING`.
- Token de invitación (reusar `StaffAccountInvitationService`) con link a la app.
- La persona completa KYC en la app → Persona + User + rol `AdminMinisterio` → vinculada a la cuenta del ministerio (sin pago).
- Se elimina `POST /licencia/solicitar-ministerio` del self-service.

## Decisiones a cerrar

- Si la cuenta del ministerio se crea en la solicitud o en la aprobación.
- Mensaje y caducidad del token.

## Criterios de aceptación

- Un ministerio no puede auto-registrarse desde la web.
- El flujo asistido termina en un `AdminMinisterio` verificado y vinculado.
