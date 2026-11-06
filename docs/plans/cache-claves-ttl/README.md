# Caché: claves, TTL e invalidación

## Objetivo

Una sola forma de cachear: claves con namespace, TTL declarado por tipo de dato e
invalidación explícita. Hoy cada clase inventa su clave, su TTL y —casi siempre— no invalida.

## Lo medido

**17 archivos** tocan `Yii::$app->cache`: 21 `get`, 21 `set`, **7 `delete`**, 0 `getOrSet`.
O sea: se escribe 3 veces más de lo que se invalida.

## Por qué importa (y no es sólo estética)

1. **Cuatro copias de "cachear un token"** (HC nacional, `IAManager`, `GoogleAuth`,
   `FcmGoogleAuth`), cada una con su cálculo de TTL: `expiresIn`, `expiresIn`,
   `max(60, expiresIn)`, `max(60, ttl-60)`. Es la misma duplicación de tokens que resolvió el
   plan `deduplicacion-http` (cerrado), vista desde el caché.
2. **TTL mágicos sin criterio común:** `300` hardcodeado en el catálogo de intents,
   `3600` en dos lugares, `315360000` (**10 años**) para términos SNOMED, `0` en RBAC (que
   significa "sin expiración"), y constantes privadas por clase.
3. **`0` con dos significados:** `get($key, 0)` es "default 0" y `set($key, …, 0)` es "nunca
   expira". El mismo literal, semántica opuesta, en el mismo módulo.
4. **Workaround por la API de Yii:** `AllowedRoutesResolver` guarda `'__empty__'` cuando el
   resultado es vacío, porque `get()` devuelve `false` en miss y no distingue "no hay rutas"
   de "no está cacheado". Eso lo resuelve el helper, no cada clase.
5. **Invalidación escasa:** 7 `delete` contra 21 `set`. Sólo RBAC tiene contador de revisión
   (`BioenlaceRbacRevision`), que es el patrón correcto y no está generalizado.

## Qué **no** es este plan

- No se decide **qué** cachear: cada módulo sigue eligiendo. Este plan es **cómo**.
- No se reemplaza `Yii::$app->cache` ni se agrega Redis: es la misma infraestructura.
- El deduplicador de IA (`RequestDeduplicator`) ya quedó resuelto y no entra acá.

## Fases

| Fase | Qué | Estado |
|------|-----|--------|
| [01](phases/01-reglas-y-helper.md) | Reglas + helper `CacheStore` | Pendiente |
| [02](phases/02-tokens.md) | Los 4 cachés de token | Transporte HTTP ya unificado |
| [03](phases/03-derivados-ia.md) | Embeddings, STT, respuesta IA, captura, SNOMED | Pendiente |
| [04](phases/04-rutas-y-cierre.md) | Rutas/acciones resueltas + cierre | Pendiente |

## Criterio de cierre

- Ninguna clase arma su clave con `md5(...)` concatenado a mano: todas pasan por `CacheStore`.
- Cada TTL es una constante con nombre y comentario de por qué ese valor.
- Toda caché con invalidación tiene una forma de invalidar (evento o revisión), no sólo TTL.
- Al cerrar: la política queda en `decisions/` y esta carpeta se borra.

## Dependencia

La fase 02 no escribe código de transporte: **consume** el `AccessTokenProvider` de
`Shared/Infrastructure/Http/Auth/`, que ya existe (plan `deduplicacion-http`, cerrado). Puede
avanzar sin esperar a otro plan.
