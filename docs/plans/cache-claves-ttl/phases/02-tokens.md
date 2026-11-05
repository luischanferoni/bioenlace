# Fase 02 — Los 4 cachés de token

## Objetivo

Que ningún módulo cachee credenciales por su cuenta. Los cuatro sitios pasan a usar el
`AccessTokenProvider` del plan de transporte.

## Tamaño

**Chico (medio día)**, **pero depende de otro plan**.

> Esta fase no escribe lógica nueva: elimina la que existe.
> Depende de `deduplicacion-http` fases 01 y 03. Si ese plan no arrancó, esta fase se pospone.

## Sitios a migrar

| Archivo | Hoy | Después |
|---------|-----|---------|
| `HttpNationalClinicalHistoryConnector` | cachea con `max(60, ttl-60)` + `invalidate()` | `OAuthClientCredentialsTokenProvider` |
| `Platform/Ai/IAManager` (líneas ~356/392) | cachea con `expiresIn` | `GoogleServiceAccountTokenProvider` |
| `Platform/Ai/Providers/Google/GoogleAuth` | cachea con `expiresIn` | idem |
| `Platform/Core/Service/Push/FcmGoogleAuth` | cachea con `max(60, expiresIn)` | idem |

## Tareas

- [ ] Migrar los 4 sitios.
- [ ] Borrar los bloques de `cache->get/set/delete` locales.
- [ ] Verificar que el TTL efectivo no cambie (si cambia, es una decisión aparte).

## Aceptación

- `grep "access_token"` no aparece en bloques de caché fuera de `Shared/Infrastructure/Http/Auth`.
- Los 3 conectores y push siguen obteniendo token en el primer uso y reutilizándolo.

## Riesgos

- **Clave de caché distinta ⇒ tokens fríos al desplegar.** Aceptable, pero conviene desplegar
  en horario de bajo uso.
- El provider debe respetar el TTL que cada proveedor informa (`expires_in` viene en segundos
  y puede no venir). Ese caso ya está en los tests de la fase 01 del otro plan.
