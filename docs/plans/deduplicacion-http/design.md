# Diseño — cliente HTTP compartido

## Ubicación

```text
web/common/components/Shared/Infrastructure/Http/
  HttpApiException.php
  HttpResponse.php
  JsonApiClient.php
  RetryPolicy.php
  Auth/
    AccessTokenProvider.php          (interfaz)
    OAuthClientCredentialsTokenProvider.php
    StaticTokenProvider.php
```

El ACL del proveedor **no** vive acá: va en
`Domain/<BC>/[<Modulo>/]Infrastructure/External/<Sistema>/<Sistema>ApiClient.php` y usa el
base. Shared sólo conoce transporte.

## Contratos

### `HttpResponse`

Inmutable. `statusCode`, `headers` (normalizados a minúsculas), `body` (array si el cuerpo
era JSON, `[]` si no), `rawBody`. Métodos `isOk()` y `header(string $name): ?string`.

Existe para no perder los headers: el conector de HC nacional resuelve el `external_id` desde
`Location`, y hoy no puede.

### `HttpApiException`

Extiende `\RuntimeException`. Campos: `provider` (string), `statusCode` (int, `0` si fue
transporte), `providerCauses` (lista de strings), `previous`. Método `isRetryable()`:
`true` para `0`, `408`, `429` y `>= 500`.

El mensaje final conserva el texto del proveedor, igual que hoy en Mercado Pago:
`"<Proveedor> rechazó la operación (HTTP 402): <message> [causes]"`. No se cambia el texto
visible al usuario sin revisar quién lo muestra.

### `RetryPolicy`

`maxAttempts` (default 2 reintentos → 3 intentos), `baseDelayMs` (200), `maxDelayMs` (2000).

Regla dura: **sólo se reintenta** si el método es idempotente (`GET`, `HEAD`, `PUT`,
`DELETE`) **o** la request lleva `Idempotency-Key`. Un `POST` sin clave nunca se reintenta.
Un `POST` con clave sí, y la clave se repite en cada intento (es lo que la hace segura).

### `JsonApiClient`

Colaboradores por constructor (DIP), con el `Client` de Yii inyectable para poder testear
sin red:

```php
new JsonApiClient(
    baseUrl: 'https://api.mercadopago.com',
    defaultHeaders: ['Authorization' => 'Bearer ' . $token],
    connectTimeout: 5,
    timeout: 20,
    logCategory: 'mercadopago',
    retry: new RetryPolicy(),
    httpClient: null,          // tests: Client con transport fake
)
```

Métodos: `request($method, $path, $body = null, $headers = [])`, `get($path, array $query = [])`,
`post`, `put`, `delete`.

Reglas de cuerpo:

| Formato | Cuándo |
|---------|--------|
| JSON (default) | Casi todo |
| `application/x-www-form-urlencoded` | `POST /oauth/token` de los 3 conectores |
| Body `string` + `Content-Type` explícito | FHIR (`application/fhir+json`) |

Mapeo de error: `extractProviderError(array $body): array{message: string, causes: string[]}`
es `protected` y sobreescribible. Default: `message` / `error` + `cause[]` con
`code: description`, que es la forma de Mercado Pago.

Logging de fábrica, una entrada por request fallida, categoría configurable:

```json
{ "event": "http.api_error", "provider": "mercadopago", "method": "POST",
  "path": "/preapproval", "status_code": 402, "provider_message": "...",
  "causes": ["...", "..."], "response": "…2000 chars…" }
```

Nunca se loguea `Authorization` ni el cuerpo completo de la request (puede llevar datos
personales).

### `Auth/AccessTokenProvider`

Interfaz de un método: `token(): string` + `invalidate(): void`.

- `OAuthClientCredentialsTokenProvider`: `POST` form-encoded, cachea con
  `expires_in - 60` en `Yii::$app->cache` (clave con `provider` + `clientId`), y
  `invalidate()` para el reintento en `401`.
- `GoogleServiceAccountTokenProvider` (fase 04): firma JWT, mismo contrato.
- `StaticTokenProvider`: token de config, para proveedores sin OAuth.

## Mapeo de los 16 archivos

| # | Archivo actual | Fase | Destino / nota |
|---|----------------|------|----------------|
| 1 | `Organization/Billing/Application/Service/MercadoPagoCheckoutService` | 02 | Se parte: `Billing/Infrastructure/External/MercadoPago/MercadoPagoApiClient` (transporte, idempotencia, `cause`) + queda en `Application/Service` el pricing ARS/IVA y la firma de webhook |
| 2 | `Clinical/Laboratory/Infrastructure/External/…/SianlabsFhirConnector` | 03 | Ya es ACL; usa base + `OAuthClientCredentialsTokenProvider` |
| 3 | `Scheduling/Agenda/Infrastructure/External/…/MsalNisFhirSchedulingConnector` | 03 | Idem (conserva el modo sin OAuth) |
| 4 | `Clinical/HistoryExchange/Infrastructure/External/…/HttpNationalClinicalHistoryConnector` | 03 | Idem + retry en `401` vía `invalidate()` |
| 5 | `Clinical/CareCohort/Infrastructure/Batch/GcsSimpleUploader` | 04 | Mover a `Infrastructure/External/Gcs/` + base |
| 6 | `Clinical/CareCohort/Infrastructure/Batch/VertexBatchPredictionClient` | 04 | Mover a `Infrastructure/External/Vertex/` + base |
| 7 | `Platform/Ai/IAManager` | 04 | Base + `StaticTokenProvider`; conserva rate limiter y `AICostTracker` |
| 8 | `Platform/Ai/HuggingFace/HuggingFaceBatchProcessor` | 04 | Base |
| 9 | `Platform/Ai/Embeddings/EmbeddingsManager` | 04 | Base |
| 10 | `Platform/Ai/Embeddings/HuggingFaceEmbeddingsClient` | 04 | Base |
| 11 | `Platform/Ai/SpeechToText/GroqSttClient` | 04 | Pasa de `curl_*` a base (timeout 120s) |
| 12 | `Platform/Ai/SpeechToText/HuggingFaceSttClient` | 04 | Base |
| 13 | `Platform/Ai/Providers/Google/GoogleAuth` | 04 | `GoogleServiceAccountTokenProvider` |
| 14 | `Platform/Core/Service/Push/FcmGoogleAuth` + `FcmHttpSender` + `PushNotificationSender` | 04 | Token provider compartido + base |
| 15 | `Platform/Assistant/WhatsApp/WhatsAppCloudApiClient` | 04 | Base; conserva la lógica de reintento de número AR |
| 16 | `Domain/Geo/Application/Service/GoogleGeocodingClient` | 05 | Mover a `Geo/Infrastructure/External/GoogleGeocoding/` + base |
| 17 | `common/models/Person/Persona` (`curl_init`) | 05 | Evaluar: si es una llamada puntual, extraer a un ACL de Person y desde ahí usar base |

## Compatibilidad

- Ningún mensaje de excepción que hoy ve el usuario cambia en fase 02 sin revisar
  `BillingCheckoutService` (lo muestra en el alta).
- La firma de webhook de Mercado Pago (`validateWebhookSignature`) **no se toca**: es
  verificación, no transporte, y ya tiene test.
- Los conectores que hoy devuelven `null`/`false` ante error (geocoding, FCM, WhatsApp)
  mantienen ese contrato: capturan `HttpApiException` en el ACL, no en el base.

## Lo que el base **no** hace

- No conoce payloads de proveedores.
- No cachea respuestas (eso es decisión de cada ACL o de `Yii::$app->cache`).
- No reintenta `POST` sin clave de idempotencia.
- No transforma errores de negocio en éxito.
