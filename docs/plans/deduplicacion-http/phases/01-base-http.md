# Fase 01 — Base HTTP compartido

## Objetivo

Existir `Shared/Infrastructure/Http/` completo, con tests, **sin tocar ningún consumidor**.
Al terminar la fase el árbol queda igual de verde y la base está lista para la 02.

## Tamaño estimado

**Medio (1–2 días).** Es código nuevo: no hay migración ni riesgo de regresión.

## Tareas

- [ ] `HttpResponse` inmutable con `statusCode`, `headers`, `body`, `rawBody`, `isOk()`, `header()`.
- [ ] `HttpApiException` con `provider`, `statusCode`, `providerCauses`, `isRetryable()`.
- [ ] `RetryPolicy` con la regla "idempotente o `Idempotency-Key`", backoff acotado.
- [ ] `JsonApiClient` con `baseUrl`, headers, timeouts, categoría de log, `Client` inyectable.
- [ ] Soporte de query params, JSON, form-encoded y body crudo con `Content-Type` explícito.
- [ ] `extractProviderError()` sobreescribible; default `message`/`error` + `cause[]`.
- [ ] Log estructurado de fábrica, body truncado a 2000 caracteres, sin `Authorization`.
- [ ] `Auth/AccessTokenProvider` + `OAuthClientCredentialsTokenProvider` + `StaticTokenProvider`.
- [ ] Tests unitarios con `Client` falso (sin red): éxito, 4xx con `cause`, 5xx retryable,
      timeout de transporte, `POST` sin clave que **no** reintenta, `POST` con clave que sí,
      log sin secretos.
- [ ] Nota de uso en `Shared/Infrastructure/README.md`.

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
