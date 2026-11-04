# Mercado Pago — Checkout Pro (suscripciones)

Guía operativa y técnica de la integración con Mercado Pago para las altas comerciales pagas (consultorio propio y clínica/centro) desde **Personal de Salud**.

- Producto: [alta-cuenta-licencia.md](../producto/alta-cuenta-licencia.md)
- Plan de origen: [alta-movil-cuentas-efectores](../plans/alta-movil-cuentas-efectores/README.md)
- Cron: [cron-produccion-hostinger.md](./cron-produccion-hostinger.md)

## Alcance

- Se usa **Checkout Pro hospedado** con una **suscripción mensual sin plan asociado y con pago pendiente** (`POST /preapproval`). Bioenlace nunca recibe PAN ni CVV.
- La cuenta, el efector, la licencia y el rol AdminEfector **se crean recién con el primer pago aprobado** confirmado por webhook.
- No se cobran ni activan licencias desde la web institucional: el único canal habilitado es la app con identidad verificada (Didit).
- Estado: **implementado y probado en unit tests**. Falta la configuración productiva, la prueba end-to-end en sandbox y los pendientes de la sección final.

## Flujo

```
App (Personal de Salud)                API Bioenlace                     Mercado Pago
────────────────────────               ─────────────                     ────────────
1. Didit aprueba identidad
2. POST licencia/registrar-efector-didit ─▶ crea BillingCheckoutAttempt
                                            (status=CREATING)
                                          ─▶ POST /preapproval ─────────▶ suscripción
                                          ◀──── id + init_point ─────────  (pending)
                                            guarda provider_subscription_id,
                                            checkout_url (status=PENDING)
3. abre checkout_url en el navegador ─────────────────────────────────▶ Checkout Pro
4. vuelve a la app (polling)
   POST licencia/estado-checkout-didit ─▶ lee estado local
                                                                   5. pago aprobado
                                          ◀──── webhook firmado ───────── subscription_authorized_payment
                                          valida X-Signature
                                          GET /authorized_payments/{id}
                                          crea org/efector/licencia + BillingPayment
                                          status=ACTIVE, paid_through_at
6. POST estado-checkout-didit → ACTIVE → habilita el espacio
```

Endpoints propios (ver [main.php](../../frontend/config/main.php)):

| Método | Ruta | Acción |
|---|---|---|
| POST | `/api/v1/licencia/registrar-efector-didit` | Alta + inicio de checkout (flujo vigente) |
| POST | `/api/v1/licencia/estado-checkout-didit` | Estado del checkout (requiere `verification_id`) |
| POST | `/api/v1/licencia/cancelar-suscripcion-didit` | Cancela renovación sin cortar el período pagado |
| POST | `/api/v1/mercado-pago-webhook` | Notificaciones firmadas (sin autenticación de usuario) |
| POST | `/api/v1/licencia/registrar-efector` | Legado con sesión autenticada |

### Estados del intento (`BillingCheckoutAttempt`)

| Estado | Significado |
|---|---|
| `CREATING` | Se está creando la suscripción en Mercado Pago. Si el proceso muere acá, el intento queda colgado. |
| `PENDING` | Suscripción creada; el usuario todavía no pagó. |
| `ACTIVE` | Primer pago aprobado y período vigente; cuenta y licencia habilitadas. |
| `FAILED` | Pago rechazado/cancelado antes de activar. El código de SISA reservado se libera y se puede reintentar con un `checkout_request_key` nuevo. |
| `CANCELLED` | Suscripción cancelada sin licencia activa. |
| `EXPIRED` | Período pagado vencido y cuenta suspendida por el cron. |

### Piezas del código

| Responsabilidad | Archivo |
|---|---|
| Cliente HTTP de Mercado Pago, precio ARS, firma de webhook | [MercadoPagoCheckoutService.php](../../common/components/Domain/Organization/Billing/Application/Service/MercadoPagoCheckoutService.php) |
| Idempotencia, alta, cancelación, reconciliación del webhook | [BillingCheckoutService.php](../../common/components/Domain/Organization/Billing/Application/Service/BillingCheckoutService.php) |
| Endpoint del webhook | [MercadoPagoWebhookController.php](../../frontend/modules/api/v1/controllers/organization/MercadoPagoWebhookController.php) |
| Cobro, facturación y suspensión por vencimiento | [BillingSubscriptionController.php](../../console/controllers/BillingSubscriptionController.php) |
| Inspección manual de intentos | [BillingCheckoutAttemptController.php](../../admin/controllers/BillingCheckoutAttemptController.php) |

Tablas: `billing_checkout_attempt`, `billing_payment`, `billing_webhook_event`, `billing_account`.
Migración: [m260829_110000_billing_mercadopago_subscription_checkout.php](../../common/migrations/m260829_110000_billing_mercadopago_subscription_checkout.php).

## Configuración

### `params.php` (no secreto, versionado)

Ya cargado en [params.php](../../common/config/params.php):

```php
'mercadoPago' => [
    'accessToken' => null,                                                        // secreto → params-local.php
    'webhookSecret' => null,                                                      // secreto → params-local.php
    'notificationUrl' => 'https://app.bioenlace.io/api/v1/mercado-pago-webhook',
    'backUrl' => 'https://app.bioenlace.io/checkout/retorno',
    'currency' => 'ARS',
    'usdArsRate' => 1450.00,                                                      // MEP vendedora, revisión trimestral
    'usdArsRateEffectiveDate' => '2026-11-01',
    'vatRate' => 0.21,
],
```

### `params-local.php` (secretos, gitignoreado por `common/config/.gitignore`)

Este archivo no se edita desde el repositorio compartido: cada entorno define su propio bloque, que reemplaza al de `params.php`.

```php
'mercadoPago' => [
    'accessToken' => getenv('MP_ACCESS_TOKEN') ?: 'APP_USR-00000000-000000-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-000000000',
    'webhookSecret' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'notificationUrl' => 'https://app.bioenlace.io/api/v1/mercado-pago-webhook',
    'backUrl' => 'https://app.bioenlace.io/checkout/retorno',
    'currency' => 'ARS',
    'usdArsRate' => 1450.00,
    'usdArsRateEffectiveDate' => '2026-11-01',
    'vatRate' => 0.21,
],
```

### Reglas que valida el código

Si alguna falla, el endpoint responde **503** (`El checkout no está configurado para este entorno`) y el log `mercadopago` dice qué falta.

| Regla | Dónde | Efecto |
|---|---|---|
| `notificationUrl` y `backUrl` con esquema `https` | `createSubscription()` | `400` con "Las URLs de retorno y notificación deben ser HTTPS." |
| `usdArsRate > 0` | `calculateArsAmount()` | `503` |
| `usdArsRateEffectiveDate` en formato `Y-m-d` | `calculateArsAmount()` | `503` |
| Antigüedad de la cotización ≤ 100 días | `calculateArsAmount()` | `503` "La cotización MEP está vencida" |
| `0 ≤ vatRate ≤ 1` | `calculateArsAmount()` | `503` |
| `accessToken` presente | `request()` | `503` si la API rechaza la petición |

### Cómo se calcula el importe

```
net_ars   = round(amount_usd * usdArsRate, 2)
vat_ars   = round(net_ars * vatRate, 2)
total_ars = net_ars + vat_ars          ← transaction_amount que se envía a Mercado Pago
```

El snapshot (USD, cotización, fecha de vigencia, neto, IVA y total) se congela en el intento. El webhook **rechaza** el pago si el `transaction_amount` o la moneda no coinciden exactamente con ese snapshot.

## Panel de Mercado Pago

En **Tus integraciones → tu aplicación**:

1. **Credenciales de producción** → copiar el *Access Token* (`APP_USR-...`). La *Public Key* no se usa: toda la integración es server-side.
2. **Credenciales de prueba** → *Access Token* (`TEST-...`). Además, en **Cuenta → Usuarios de prueba**, crear un usuario **comprador** para poder pagar en sandbox.
3. **Webhooks → Configurar notificaciones**:
   - URL de producción: `https://app.bioenlace.io/api/v1/mercado-pago-webhook`
   - URL de test: la misma o un túnel HTTPS (`ngrok`/`cloudflared`) si se prueba local.
   - Eventos: marcar **Planes y suscripciones**. Es un solo checkbox que agrupa los dos topics que procesa el código: `subscription_preapproval` y `subscription_authorized_payment` (también envía `subscription_preapproval_plan`, que se ignora porque no usamos planes).
   - **Guardar** y luego **Revelar** la *clave secreta*: es el `webhookSecret`.
4. Completar **Datos de negocio** (CUIT y demás) para habilitar las credenciales productivas.

Notas:

- Para suscripciones, Mercado Pago **no** toma la URL del panel: la `notification_url` efectiva es la que se envía en cada `POST /preapproval` (el código ya la manda desde `params`). Del panel igual hace falta el secreto.
- La respuesta del endpoint debe ser `200`/`201` en menos de 22 segundos; si no, Mercado Pago reintenta. El código responde 200 y consulta el recurso real en la API.
- Devuelve **401** si la firma no valida y **500** si el recurso notificado no se puede procesar (por ejemplo, un `data.id` inexistente). Ambos casos generan reintentos.

## Prueba en sandbox

### Preparación

1. Copiar el token `TEST-...` y el secreto en `params-local.php`.
2. Verificar el token:

```bash
curl -s -H "Authorization: Bearer $MP_ACCESS_TOKEN" https://api.mercadopago.com/users/me
```

3. Ingresar a la app **Personal de Salud** con un usuario de prueba y recorrer el alta comercial (consultorio o clínica/centro). También se puede llamar la API directamente:

```bash
curl -i -X POST https://app.bioenlace.io/api/v1/licencia/registrar-efector-didit \
  -H "Content-Type: application/json" \
  -d '{
    "perfil": "ESPACIO_ATENCION",
    "sector": "PRIVADO",
    "verification_id": "didit_xxx",
    "checkout_request_key": "0f3c1d2e3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b",
    "payer_email": "test_user_123@testuser.com",
    "contact_phone": "1155554444",
    "efector": {
      "nombre": "Consultorio Demo",
      "domicilio": "Av. Siempre Viva 742",
      "telefono": "1144443333",
      "id_localidad": 1234
    },
    "plan": {
      "classes": { "AMB": { "attentions_per_month": 200, "max_pes": 1 } }
    }
  }'
```

`checkout_request_key` debe ser **48 caracteres hexadecimales**. Es la clave de idempotencia: repetir la llamada con la misma clave y los mismos datos devuelve el mismo `checkout_url` sin crear otra suscripción.

Respuesta esperada (`201`):

```json
{
  "success": true,
  "data": {
    "checkout_key": "0f3c1d2e...",
    "checkout_url": "https://www.mercadopago.com.ar/subscriptions/checkout?preapproval_id=...",
    "status": "PENDING",
    "provider_subscription_status": "pending",
    "amount_usd": 1.0,
    "exchange_rate": 1450.0,
    "amount_net_ars": 1450.0,
    "vat_rate": 0.21,
    "vat_ars": 304.5,
    "amount_total_ars": 1754.5,
    "currency": "ARS"
  }
}
```

### Escenarios a cubrir

| Escenario | Cómo | Resultado esperado |
|---|---|---|
| Pago aprobado | Tarjeta de prueba "APRO" + usuario comprador | `status=ACTIVE`, `paid_through_at` con el mismo día del mes siguiente, licencia activa |
| Pago rechazado | Tarjeta "OTHE" | `status=FAILED`, `reserved_sisa_code` liberado, sin cuenta ni licencia |
| Pago pendiente | Tarjeta "CONT" (efectivo) | El intento queda `PENDING`; se activa recién cuando el pago se acredita |
| Webhook repetido | Reenviar el mismo evento | Segundo POST responde 200 sin duplicar `billing_payment` (dedupe por `event_key`) |
| Reintento tras rechazo | Nuevo `checkout_request_key` | Nuevo intento; no se duplica user/persona/efector |
| Cancelación | `POST licencia/cancelar-suscripcion-didit` | `provider_subscription_status=cancelled`; la licencia sigue activa hasta `paid_through_at` |
| Firma inválida | POST sin `x-signature` o con secreto distinto | `401` y log `Firma inválida en webhook de Mercado Pago.` |

Las tarjetas de prueba oficiales y sus nombres (APRO/OTHE/CONT) están en la documentación de Mercado Pago; usar siempre un **usuario de prueba comprador**, nunca una cuenta real.

### Verificación

```sql
-- Últimos intentos
SELECT checkout_key, status, provider_subscription_status, provider_status,
       amount_total_ars, paid_through_at
FROM billing_checkout_attempt
ORDER BY id DESC LIMIT 5;

-- Eventos recibidos y su procesamiento
SELECT event_key, topic, resource_id, status, processed_at
FROM billing_webhook_event
ORDER BY id DESC LIMIT 10;

-- Cobros registrados
SELECT provider_payment_id, provider_subscription_id, amount_ars, paid_at
FROM billing_payment
ORDER BY id DESC LIMIT 5;
```

Logs: canal `mercadopago` en `frontend/runtime/logs/` y `console/runtime/logs/`. Eventos útiles: `mercadopago.api_error`, `mercadopago.checkout_creation_failed`, `mercadopago.checkout_attempt_update_failed`, `mercadopago.webhook_ignored`.

Admin: **Billing checkout attempts** muestra el intento con su payload del proveedor, útil para diagnosticar importes o estados inesperados.

### Reenviar un webhook firmado a mano

Sirve para verificar la firma sin esperar un pago real. Usar el `id` de un recurso que exista en la cuenta de prueba y un `id` de evento nuevo (si se repite, el código lo descarta por deduplicación):

```bash
TS=$(date +%s)
REQUEST_ID="$(uuidgen | tr 'A-Z' 'a-z')"
DATA_ID="a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6"
MANIFEST="id:$(printf '%s' "$DATA_ID" | tr 'A-Z' 'a-z');request-id:$REQUEST_ID;ts:$TS;"
V1=$(printf '%s' "$MANIFEST" | openssl dgst -sha256 -hmac "$MP_WEBHOOK_SECRET" | awk '{print $NF}')

curl -i -X POST https://app.bioenlace.io/api/v1/mercado-pago-webhook \
  -H "Content-Type: application/json" \
  -H "x-signature: ts=$TS,v1=$V1" \
  -H "x-request-id: $REQUEST_ID" \
  -d "{\"id\":\"$(uuidgen)\",\"type\":\"subscription_preapproval\",\"action\":\"subscription_preapproval.updated\",\"data\":{\"id\":\"$DATA_ID\"}}"
```

Payload real de un cobro recurrente (lo que envía Mercado Pago):

```json
{
  "id": 123456789,
  "live_mode": true,
  "type": "subscription_authorized_payment",
  "action": "subscription_authorized_payment.created",
  "date_created": "2026-11-04T10:00:00.000-03:00",
  "data": { "id": "6069b7c4e2a1f3001b2c3d4e" }
}
```

El manifest que valida el código (`MercadoPagoCheckoutService::validateWebhookSignature`) es exactamente:

```
id:<data.id en minúsculas>;request-id:<x-request-id>;ts:<ts>;
```

comparado con HMAC-SHA256 y `hash_equals` contra `v1`.

## Producción

- [ ] Credenciales `APP_USR-...` y secreto productivo en `params-local.php` (nunca en `params.php`).
- [ ] `notificationUrl` y `backUrl` con HTTPS y host definitivo.
- [ ] Webhook productivo con **Planes y suscripciones** activo.
- [ ] Cron diario `billing-subscription/expire-paid-through` (ver [cron-produccion-hostinger.md](./cron-produccion-hostinger.md)).
- [ ] Cron de purga `commercial-signup/purge-drafts` activo.
- [ ] Cotización MEP cargada con fecha vigente y recordatorio trimestral de revisión.
- [ ] Una prueba real de bajo importe con reembolso manual inmediato.
- [ ] Logs `mercadopago` monitoreados (401/500 repetidos indican secreto o `data.id` inválidos).

## Pendientes

Estado tomado de [03-altas-pagas.md](../plans/alta-movil-cuentas-efectores/phases/03-altas-pagas.md). Los ítems de Mercado Pago son los primeros.

### 1. Configuración productiva y prueba real (bloqueante)

Falta cargar credenciales, secreto, URLs y cotización, y correr el circuito completo. Ejemplo de verificación mínima antes de habilitar ventas:

```bash
# 1. token válido y de la cuenta correcta
curl -s -H "Authorization: Bearer $MP_ACCESS_TOKEN" https://api.mercadopago.com/users/me | jq '.id, .email'

# 2. la cotización no está vencida (debe devolver 201, no 503)
curl -i -X POST https://app.bioenlace.io/api/v1/licencia/registrar-efector-didit \
  -H "Content-Type: application/json" -d @checkout.json
```

### 2. Reembolsos y contracargos

Hoy los topics `topic_chargebacks_wh` (contracargos) y `topic_claims_integration_wh` (reclamos/reembolsos) **no están en el allowlist** de `processWebhook`, así que se registran como `mercadopago.webhook_ignored`. Si se devuelve el dinero, la licencia sigue activa hasta `paid_through_at`.

Qué habría que decidir y construir:

1. Decisión de negocio: ¿suspender al recibir el reembolso o dejar correr el período pagado?
2. Agregar los topics al allowlist y un handler que consulte el recurso.

Ejemplo de la forma que tendría el handler (mismo patrón que `processAuthorizedPayment`):

```php
if ($topic === 'topic_chargebacks_wh') {
    $chargeback = $this->mercadoPago()->getChargeback($resourceId);   // GET /v1/chargebacks/{id}
    $paymentId = (string) ($chargeback['payment_id'] ?? '');
    $payment = BillingPayment::findOne(['provider_payment_id' => $paymentId]);
    if ($payment === null) {
        return; // evento de otro producto
    }
    $payment->status = BillingPayment::STATUS_REFUNDED;
    $payment->payload_json = $this->encodeJson($chargeback);
    $payment->save(false, ['status', 'payload_json', 'updated_at']);
    // política pendiente: revocar acceso ya, o solo marcar para revisión asistida
}
```

Mientras no esté implementado, la salida operativa es revisar a mano el panel de Mercado Pago y suspender la cuenta desde el admin.

### 3. Reanudar errores y rechazos sin duplicar

- Rechazo: ya funciona (el intento pasa a `FAILED`, se libera el código SISA y la app pide un `checkout_request_key` nuevo).
- Timeout durante la creación: el intento queda en `CREATING` sin `checkout_url`. Hoy la app responde 503 y el usuario puede reintentar con la misma clave, pero no hay barrido automático.

Ejemplo de barrido propuesto (comando nuevo + cron cada 10 minutos):

```php
// console/controllers/BillingSubscriptionController.php
public function actionReconcileCreating(): int
{
    $cutoff = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    $stale = BillingCheckoutAttempt::find()
        ->where(['status' => BillingCheckoutAttempt::STATUS_CREATING])
        ->andWhere(['<', 'created_at', $cutoff])
        ->andWhere(['provider_subscription_id' => null])
        ->all();

    foreach ($stale as $attempt) {
        $attempt->status = BillingCheckoutAttempt::STATUS_FAILED;
        $attempt->provider_status = 'CHECKOUT_ABANDONED';
        $attempt->reserved_sisa_code = null;
        $attempt->save(false, ['status', 'provider_status', 'reserved_sisa_code', 'updated_at']);
    }

    $this->stdout('Intentos abandonados liberados: ' . count($stale) . "\n");
    return ExitCode::OK;
}
```

### 4. Rol clínico opcional del titular (REFEPS)

Independiente de Mercado Pago. Consultorio crea siempre AdminEfector; si el titular además quiere atender, hay que agregar la asignación clínica cumpliendo la política REFEPS. El cobro no cambia.

Ejemplo de criterio: al confirmar el pago, `SignUpInstitutionalEfector::register()` crea el efector y el AdminEfector; la asignación clínica se resuelve después, desde la web, cuando REFEPS confirme documento.

### 5. Catálogo SISA local

Los códigos de efectores públicos se validan contra el catálogo local; si el código no está cargado, el alta se rechaza antes del checkout. Falta documentar y automatizar la actualización periódica con el archivo oficial.

Ejemplo de verificación antes de un alta pública:

```sql
-- El catálogo local es la tabla `efectores` (modelo Organization\Efector)
SELECT id_efector, codigo_sisa, nombre, estado
FROM efectores
WHERE codigo_sisa = '0000000000';
```

Solo un efector con `estado` activo puede reutilizarse para un alta pública; si no aparece, el alta se rechaza antes de crear la suscripción.

### 6. Servicios y personal después del alta

Después de la activación, AdminEfector define servicios y asigna personal en la web. No se precargan servicios ni personas.

### 7. Cron

Los cron de licencias están documentados en [cron-produccion-hostinger.md](./cron-produccion-hostinger.md):

| Frecuencia | Comando | Efecto |
|---|---|---|
| 01:00 diario | `billing-subscription/expire-paid-through` | Suspende cuentas con `paid_through_at` vencido y pasa el intento a `EXPIRED`. |
| 01:30 diario | `commercial-signup/purge-drafts` | Borra borradores `EXPIRED` con más de 30 días. |

Sin el primero, una suscripción cancelada o impaga **nunca** pierde el acceso.

### 8. Rotación del secreto de webhook

El secreto no vence, pero conviene rotarlo. Al hacer *Reset* en el panel hay una ventana en la que llegan notificaciones firmadas con el secreto viejo: los POST responderán 401 y Mercado Pago reintenta cada 15 minutos. Procedimiento: actualizar `params-local.php`, desplegar, y recién después resetear en el panel (o aceptar la ventana de reintentos, que se recupera sola porque los reintentos vuelven a firmarse con el secreto vigente).

### 9. Planes con `preapproval_plan` (opcional)

Hoy la suscripción se crea sin plan asociado (`payer_email` obligatorio). Si en el futuro se quiere un plan único con precio fijo, habría que:

1. Crear el plan en el panel o por API (`POST /preapproval_plan`).
2. Enviar `preapproval_plan_id` en lugar de `auto_recurring` en `createSubscription()`.
3. Quitar la exigencia de `payer_email` en ese camino (el pagador lo define Mercado Pago).

No es necesario para el modelo actual: cada efector tiene un precio distinto según volumen y perfil.
