# Fase 03 — OAuth `client_credentials` ×3

## Objetivo

Una sola implementación del flujo `client_credentials` y un solo manejo de token vencido.

## Tamaño estimado

**Medio (1 día).** Tres conectores con la misma forma; el trabajo es mecánico y el riesgo
bajo, porque son integraciones que no están en producción crítica.

## Situación actual

| Conector | Token | Cachea | Maneja `401` |
|----------|-------|--------|--------------|
| Sianlabs (LIS) | propio | No (sólo en memoria del request) | No |
| NIS FHIR (agenda) | propio | No | No |
| HC nacional | propio | Sí (`Yii::$app->cache`) | Sí (invalida y reintenta) |

Los tres arman el `POST` form-encoded, parsean `access_token`/`expires_in` y eligen un
mensaje de error distinto para lo mismo.

## Tareas

- [x] `OAuthClientCredentialsTokenProvider` en `Shared/Infrastructure/Http/Auth` con cache
      (`expires_in - 60`) e `invalidate()`.
- [x] Migrar `SianlabsFhirConnector` al base + provider.
- [x] Migrar `MsalNisFhirSchedulingConnector` (conserva el modo "sin OAuth" cuando no hay
      `tokenUrl`/`clientId`).
- [x] Migrar `HttpNationalClinicalHistoryConnector`, preservando el retry en `401` y el
      uso del header `Location` (ahora posible porque `HttpResponse` expone headers).
- [x] Unificar el mensaje de error de OAuth: hoy cada conector dice algo distinto.
- [x] Tests del provider: cache hit, cache miss, `expires_in` ausente, `invalidate()`.

## Aceptación

- Los 3 conectores usan el mismo provider y no tienen `getAccessToken` propio.
- `ArchitectureHttpUsageTest` verde (los 3 ya estaban en `Infrastructure/External`, así que
  el baseline no cambia; el que cambia es la duplicación de token).
- El conector de HC nacional sigue resolviendo el `external_id` desde `Location`.

## Riesgos

- El cache de token de HC nacional usa una clave con `connectorKey` + `clientId`: mantener
  el mismo esquema para no invalidar tokens en producción al desplegar.
- Algunos entornos tienen credenciales vacías y hoy fallan en `getAccessToken`. El provider
  debe fallar con el mismo tipo de excepción que el conector espera.

## Fuera de alcance

- Google service account (fase 04): firma JWT, no es `client_credentials`.
