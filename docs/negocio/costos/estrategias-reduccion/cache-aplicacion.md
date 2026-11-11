# Caché de aplicación (Yii)

Contextos medibles en [catálogo de IA](../../../producto/catalogo-usos-ia.md) (`AICostTracker::por_contexto`).

## Qué hace

Evita **llamadas enteras** al proveedor si el mismo `prompt + contexto + tipoModelo` ya se resolvió dentro del TTL (`ia_cache_ttl`, etc. en `params.php`). Clave: `ia_response_` + `md5(...)`.

**No es** context caching de Vertex: no aparece `cachedContentTokenCount`; es ahorro del 100 % de esa llamada.

## Estado en Bioenlace

| Parámetro | Valor versionado | Notas |
|-----------|------------------|-------|
| `ia_cache_desactivado` | **`true`** | Llega así a producción: `params-local.php` no lo sobreescribe, así que hoy **el caché de aplicación y el dedup exacto están apagados** en todos los contextos de `IAManager::consultarIA` (preprocess, guide, motivos batch, insights, codificación). Es un interruptor de desarrollo que quedó encendido. |
| `ia_cache_ttl` | `604800` (7 d) | TTL si se activa. |
| `ia_usage_tracking_habilitado` | `true` | Permite medir hit rate antes de activar. |

Activar (`false`) **por contexto**, no global: el chat libre casi no repite prompts, mientras que `preprocess` con mensajes cortos y los contextos con plantilla fija sí. Medir primero con `AICostTracker` (`evitada_por_cache`, `evitada_por_dedup`).

## Reducción estimada (orientativa)

**40–60 %** del costo de IA **solo** si hay muchas entradas repetidas. En chat libre del asistente el hit rate suele ser **bajo**.

## Medición

`AICostTracker::registrarEvitada('cache', $contexto)` cuando `IAManager` lee de Yii cache.

**No incluido** en columnas de [costos-api.md](../costos-api.md) ni [impuestos-argentina.md](../impuestos-argentina.md) hasta validar en producción.

## Ver también

- [context-caching-implicita.md](context-caching-implicita.md)
- [monitoreo.md](monitoreo.md)
