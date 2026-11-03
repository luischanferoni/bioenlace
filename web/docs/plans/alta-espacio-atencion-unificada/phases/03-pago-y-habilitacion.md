# Fase 03 — Pago, activación y asistente AdminEfector

## Objetivo

Habilitar el efector, la licencia y el rol administrativo únicamente al conciliar el primer pago aprobado.

## Trabajo

- [ ] Conectar borrador con `BillingCheckoutAttempt`; congelar selección y cotización en el snapshot del checkout.
- [ ] Reutilizar Mercado Pago Checkout Pro mensual y el historial de tasa/IVA del servidor.
- [ ] Definir política de reintento para timeout de creación de suscripción y estados del proveedor, preservando idempotencia.
- [ ] Validar firma webhook y consultar estado del recurso antes de activar el alta.
- [ ] Activar efector/cuenta/entitlements y crear PES AdminEfector en una transacción idempotente tras pago aprobado.
- [ ] Asegurar que webhook repetido y concurrencia de callback/reintento no dupliquen efector, cuenta, entitlements, pagos ni PES.
- [ ] Mantener borrador y checkout sin privilegios mientras pago sea pendiente, rechazado, cancelado o expirado.
- [ ] Después de activación, habilitar login móvil AdminEfector y acceso al asistente de administración.
- [ ] Guiar al AdminEfector: configurar servicios del efector; luego asignar personal existente.
- [ ] Habilitar camino separado para que el propietario se agregue a sí mismo como profesional, sujeto a servicio activo y política REFEPS.

## Criterios de aceptación

- La respuesta del cliente nunca puede declarar un pago aprobado ni solicitar el rol AdminEfector.
- El rol y licencia se crean solo tras webhook firmado, recurso conciliado y pago aprobado.
- Un fallo durante activación es reintentable y converge a una sola cuenta activa.
- AdminEfector puede configurar el centro, pero no adquiere por ello rol clínico.
