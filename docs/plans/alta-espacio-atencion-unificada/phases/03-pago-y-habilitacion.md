# Fase 03 — Pago, activación y asistente AdminEfector

## Objetivo

Habilitar el efector, la licencia y el rol administrativo únicamente al conciliar el primer pago aprobado.

## Trabajo

- [x] Conectar borrador con `BillingCheckoutAttempt`; congelar selección y cotización en el snapshot del checkout. (`markCheckoutPending` + snapshot de efector/plan/FX/IVA en el intento; el webhook concilia el borrador como `ACTIVE` al aprobar el primer pago.)
- [x] Reutilizar Mercado Pago Checkout Pro mensual y el historial de tasa/IVA del servidor.
- [x] Definir política de reintento para timeout de creación de suscripción y estados del proveedor, preservando idempotencia.
- [x] Validar firma webhook y consultar estado del recurso antes de activar el alta.
- [x] Activar efector/cuenta/entitlements y crear PES AdminEfector en una transacción idempotente tras pago aprobado.
- [x] Asegurar que webhook repetido y concurrencia de callback/reintento no dupliquen efector, cuenta, entitlements, pagos ni PES.
- [x] Mantener borrador y checkout sin privilegios mientras pago sea pendiente, rechazado, cancelado o expirado.
- [x] Después de activación, habilitar login móvil AdminEfector y acceso al asistente. (`StaffMobileAccessService` admite `AdminEfector`; la app abre el panel de gestión y la pestaña Asistente.)
- [x] Guiar al AdminEfector: configurar servicios del efector; luego asignar personal existente. (Mismos flows que web: `servicio-teleconsulta.configurar-efector-flow` y `profesional-efector-servicio.crear-flow`.)
- [x] Panel admin de solo lectura para checkouts de suscripción (estado, importes, efector y vínculo a la cuenta de licencia).
- [x] Habilitar camino separado para que el propietario se agregue a sí mismo como profesional, sujeto a servicio activo y política REFEPS. (Usa el alta profesional estándar; no depende del rol admin.)

## Criterios de aceptación

- La respuesta del cliente nunca puede declarar un pago aprobado ni solicitar el rol AdminEfector.
- El rol y licencia se crean solo tras webhook firmado, recurso conciliado y pago aprobado.
- Un fallo durante activación es reintentable y converge a una sola cuenta activa.
- AdminEfector puede configurar el centro, pero no adquiere por ello rol clínico.
