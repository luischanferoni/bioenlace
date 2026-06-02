# Monitoreo de costos de IA

## Componente: `AICostTracker`

Ubicación: `web/common/components/Ai/Cost/AICostTracker.php`

| Métrica | Cuándo se incrementa |
|---------|----------------------|
| `evitada_por_cache` | Respuesta desde caché Yii |
| `evitada_por_dedup` | `RequestDeduplicator` |
| `evitada_por_cpu` / `validacion` | Corrección CPU o prompt inválido |
| `llamada_simulada` | Runner de pruebas / tests (`iniciarEjecucionPrueba`) |
| `llamada_real` | HTTP OK a Gemini con tracking activo |
| `tokens.*` | `usageMetadata` de respuesta Google (+ simulación si aplica) |
| `tokens.cached_content_token_count_simulado` | Estimación local con `vertex_context_cache_simulado` |
| `por_contexto` | Desglose por segundo argumento de `consultarIA` |

### Habilitar medición en staging/producción

En `web/frontend/config/params.php`:

```php
'ia_usage_tracking_habilitado' => true,
'vertex_context_cache_simulado' => true, // opcional: split estable/variable + estimación local
```

Mientras esté en `false`, en producción **no** se acumulan tokens ni `llamada_real` (solo contadores de prueba cuando corre el runner).

### Interpretar context caching

```php
$resumen = AICostTracker::getResumen();
$ratio = $resumen['tokens']['ratio_input_en_cache']; // cached / prompt
$billable = $resumen['tokens']['billable_input_token_count']; // prompt - cached
```

`promptTokenCount` de Google **incluye** los tokens cacheados; el input facturable a tarifa plena es la diferencia.

Logs: categoría Yii `ia-cost`.

## Pruebas sin gastar API

[pruebas-costos-ia.md](../pruebas-costos-ia.md) describe conversaciones JSON y `php yii costos/ejecutar-conversacion`. Esa ruta **simula** IA (`debeSimularIA()`); no mide `cachedContentTokenCount` real.

Tests unitarios:

```bash
cd web && vendor/bin/codecept run unit common/tests/unit/costos/AICostTrackerTest
```

## Pendiente / mejoras

| Ítem | Estado |
|------|--------|
| Controller CLI/web `costos/ejecutar-conversacion` | Documentado; verificar despliegue en el entorno |
| Persistencia de métricas (BD, Prometheus) | No implementado; hoy solo memoria del request o resumen en respuesta de prueba |
| Dashboard por efector | Pendiente |
| Alertas de umbral | Pendiente (ver § gobernanza en [estrategias-api.md](./estrategias-api.md)) |
| Métricas STT (minutos servidor, fallback, calidad device vs server) | Pendiente; definición en [stt.md § Monitoreo](./stt.md#monitoreo-pendiente-de-implementación) |

## Gobernanza

- Revisar precios Vertex cada 6–12 meses.
- Cuotas por institución cuando exista facturación interna.
- Comparar `ratio_input_en_cache` con el supuesto **~25 %** (favorable) de [costos-api.md](../costos-api.md); COGS base usa columna **sin caché**.
