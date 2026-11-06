# `Shared/Infrastructure`

Infra técnica transversal del monorepo (antes `Platform/Infra/`).

Subcarpetas actuales: `Http/`, `Log/`, `Migration/`, `Requests/`.

ACL de sistemas de negocio **no** viven aquí — van al módulo/BC dueño.

## `Http/` — el único transporte HTTP

Todo cliente de API externa usa `JsonApiClient`: timeouts, reintentos, parseo, mapeo de
error y log vienen de fábrica. El ACL aporta base URL, headers, token y, si el proveedor
tiene otra forma de error, `extractProviderError()`.

```php
$client = new JsonApiClient(
    baseUrl: 'https://api.mercadopago.com',
    defaultHeaders: ['Authorization' => 'Bearer ' . $token],
    logCategory: 'mercadopago',
    provider: 'mercadopago',
    providerLabel: 'Mercado Pago',
    retry: new RetryPolicy(),
);

$response = $client->post('/preapproval', $payload, ['X-Idempotency-Key' => $key]);
$response->body();              // array, o [] si el cuerpo no era JSON
$response->header('Location');  // headers normalizados a minúsculas

// Query params como array, y contexto propio del ACL para el log de error
$client->get('/Appointment', ['patient' => 'p-1'], [], ['job_id' => 42]);
```

- **No reintenta** un `POST` sin clave de idempotencia; sí un método idempotente
  (`GET`, `HEAD`, `PUT`, `DELETE`) o cualquier request con `Idempotency-Key`.
- **Falla** con `HttpApiException` (`provider()`, `statusCode()`, `providerCauses()`,
  `responseBody()`, `isRetryable()`): el ACL decide si devuelve `null`, traduce el texto o propaga.
- **Log** `http.api_error` con `provider`, `path`, `status_code`, `causes` y el `logContext`
  que aporte el ACL. Nunca loguea `Authorization` ni el cuerpo de la request.
- **Formatos de body**: array → JSON (lo serializa el base para no perder el `Content-Type`),
  array + urlencoded → form, string → contenido crudo, `MultipartBody` → `multipart/form-data`
  en memoria (audio, imágenes) sin archivo temporal.
- **Token**: `Auth/AccessTokenProvider`. `OAuthClientCredentialsTokenProvider` y
  `GoogleServiceAccountTokenProvider` cachean e `invalidate()` sirve para reintentar un `401`;
  `StaticTokenProvider` para API keys. Los tres conectores OAuth del repo (LIS, agenda FHIR,
  HC nacional) y los dos de Google (Vertex/Gemini, FCM) usan el mismo provider cada uno.

Tests: `common/tests/unit/shared/infrastructure/http/` (transporte falso, sin red). Para
facades estáticas hay `JsonApiClient::$transportOverride` (`@internal`, sólo tests).
