# Fase 02 — Billing / Mercado Pago

## Objetivo

Sacar el transporte de `Application/Service` y validar el diseño del base contra el
consumidor más delicado del repo: el cobro de suscripciones.

## Tamaño estimado

**Medio (1 día).** Pocos métodos (`POST /preapproval`, `GET /preapproval/{id}`,
`GET /authorized_payments/{id}`, `PUT /preapproval/{id}`), pero mueve dinero.

## Por qué esta fase va segunda

Es el único consumidor que ya tiene `X-Idempotency-Key`, errores con `cause[]` y un
`try/catch` que el usuario final ve en el alta. Si el base no soporta eso, es mejor
descubrirlo acá que en la fase 04 con 8 archivos migrados.

## Tareas

- [ ] Crear `Domain/Organization/Billing/Infrastructure/External/MercadoPago/MercadoPagoApiClient`
      con los 4 endpoints sobre `JsonApiClient`.
- [ ] `MercadoPagoCheckoutService` conserva pricing ARS/IVA, validaciones de entrada y
      `validateWebhookSignature` (no se toca la firma), y delega el HTTP.
- [ ] Revisar que el mensaje de error que ve el usuario en `BillingCheckoutService` no cambie.
- [ ] Sacar `MercadoPagoCheckoutService.php` de `applicationHttp` y `adHoc` del baseline.
- [ ] Tests: el cliente con `Client` falso (éxito, 402 con `cause`, reintento de `POST` con clave).
- [ ] Confirmar comportamiento ante timeout: un `POST /preapproval` con clave puede reintentarse
      sin duplicar suscripción (verificar con el sandbox de MP antes de cerrar la fase).

## Aceptación

- `MercadoPagoCheckoutServiceTest` sigue verde sin cambios.
- `ArchitectureHttpUsageTest` verde con 2 entradas menos en el baseline.
- `Billing/Application/Service` no importa `yii\httpclient`.

## Riesgos

- **Doble cobro** si el reintento se implementa mal. La clave de idempotencia debe ser la
  misma en todos los intentos y generarse una vez por operación, no por intento.
- Cambiar el texto de la excepción rompe el mensaje del alta. Comparar antes/después.

## Fuera de alcance

- Webhooks: la verificación de firma y la deduplicación de eventos quedan como están.
