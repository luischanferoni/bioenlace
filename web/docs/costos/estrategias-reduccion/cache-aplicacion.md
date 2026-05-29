# Caché de aplicación (Yii)

## Qué hace

Evita **llamadas enteras** al proveedor si el mismo `prompt + contexto + tipoModelo` ya se resolvió dentro del TTL (`ia_cache_ttl`, `correccion_cache_ttl`, etc. en `params.php`). Clave: `ia_response_` + `md5(...)`.

**No es** context caching de Vertex: no aparece `cachedContentTokenCount`; es ahorro del 100 % de esa llamada.

## Estado en Bioenlace

| Parámetro | Valor típico dev | Notas |
|-----------|------------------|--------|
| `ia_cache_desactivado` | `true` | Fuerza llamadas reales en desarrollo |
| `correccion_cache_desactivado` | `true` | Idem corrección |

En producción se puede activar (`false`) donde haya repetición (corrección, mismos lotes).

## Reducción estimada (orientativa)

**40–60 %** del costo de IA **solo** si hay muchas entradas repetidas. En chat libre del asistente el hit rate suele ser **bajo**.

## Medición

`AICostTracker::registrarEvitada('cache', $contexto)` cuando `IAManager` lee de Yii cache.

**No incluido** en columnas de [costos-api.md](../costos-api.md) ni [impuestos-argentina.md](../impuestos-argentina.md) hasta validar en producción.

## Ver también

- [context-caching-implicita.md](./context-caching-implicita.md)
- [monitoreo.md](./monitoreo.md)
