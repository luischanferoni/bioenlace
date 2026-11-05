# Fase 01 — Base HTTP compartido

## Objetivo

Existir `Shared/Infrastructure/Http/` completo, con tests, **sin tocar ningún consumidor**.
Al terminar la fase el árbol queda igual de verde y la base está lista para la 02.

## Tamaño estimado

**Medio (1–2 días).** Es código nuevo: no hay migración ni riesgo de regresión.

## Tareas

- [x] `HttpResponse` inmutable con `statusCode`, `headers`, `body`, `rawBody`, `isOk()`, `header()`.
- [x] `HttpApiException` con `provider`, `statusCode`, `providerCauses`, `isRetryable()`.
- [x] `RetryPolicy` con la regla "idempotente o `Idempotency-Key`", backoff acotado.
- [x] `JsonApiClient` con `baseUrl`, headers, timeouts, categoría de log, `Client` inyectable.
- [x] Soporte de query params, JSON, form-encoded y body crudo con `Content-Type` explícito.
- [x] `extractProviderError()` sobreescribible; default `message`/`error` + `cause[]`.
- [x] Log estructurado de fábrica, body truncado a 2000 caracteres, sin `Authorization`.
- [x] `Auth/AccessTokenProvider` + `OAuthClientCredentialsTokenProvider` + `StaticTokenProvider`.
- [x] Tests unitarios con `Client` falso (sin red): éxito, 4xx con `cause`, 5xx retryable,
      timeout de transporte, `POST` sin clave que **no** reintenta, `POST` con clave que sí,
      log sin secretos.
- [x] Nota de uso en `Shared/Infrastructure/README.md`.

## Aceptación

- `vendor/bin/codecept run unit shared` verde.
- `ArchitectureHttpUsageTest` sigue verde sin cambios (nadie migró todavía).
- El base permite reproducir en test el caso Mercado Pago (POST con idempotencia + `cause[]`).

## Riesgos

- **Sobre-diseñar.** El base debe resolver lo que los 16 archivos necesitan hoy, no un
  framework. Si aparece una necesidad que todavía no existe, no se agrega.
- Colisión de nombres con `yii\httpclient\Client`: el nuestro se llama `JsonApiClient` a
  propósito.

## Fuera de alcance

- Migrar cualquier archivo. Eso empieza en la 02.
