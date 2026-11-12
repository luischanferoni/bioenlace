# Monitoreo de costos de IA

## Componente: `AICostTracker`

Ubicación: `web/common/components/Platform/Ai/Cost/AICostTracker.php`

| Métrica | Cuándo se incrementa |
|---------|----------------------|
| `evitada_por_cache` | Respuesta desde caché Yii |
| `evitada_por_dedup` | `RequestDeduplicator::buscarExacto` acierta dentro del mismo request (mismo prompt normalizado + contexto) |
| `evitada_por_cpu` / `validacion` | Corrección CPU o prompt inválido |
| `llamada_simulada` | Runner de pruebas / tests (`iniciarEjecucionPrueba`) |
| `llamada_real` | HTTP OK a Gemini con tracking activo |
| `tokens.*` | `usageMetadata` de respuesta Google (+ simulación si aplica) |
| `tokens.cached_content_token_count_simulado` | Estimación local con `vertex_context_cache_simulado` |
| `por_contexto` | Desglose por segundo argumento de `consultarIA` |

### Observación del match difuso (`ia-dedupe`)

`RequestDeduplicator::observarSimilitud` compara el prompt con lo ya resuelto **en el mismo
request** y, si la similitud alcanza `RequestDeduplicator::SIMILITUD_MINIMA` (0.85), registra
en la categoría `ia-dedupe`:

- `event`: `ia_dedupe.similitud_observada`
- `tipo`, `similitud`, `umbral`, `candidatos`
- `prompt_hash` / `candidato_hash` (sha256 del prompt normalizado) y `prompt_len`

**No** se loguea el texto (es contenido clínico) y el match difuso **no** devuelve respuestas:
solo se mide para evaluar más adelante si conviene alguna forma de reutilización.

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

### Telemetría persistente (JSONL + agregado diario)

`AICostTracker` sigue siendo el acumulador en memoria (tests y simulación). Para producción, cada llamada real deja una **línea JSONL** en `@common/runtime/ai-usage/ai-usage-YYYY-MM-DD.jsonl`:

```json
{"ts":"...","ctx":"analisis-consulta","model":"gemini-2.5-flash-lite",
 "prompt":1200,"cached":300,"output":600,"thoughts":0,
 "usd":0.00033,"ms":840,"ok":true,"efector":42,"enc":991,"hash":"sha256:..."}
```

- **No** guarda prompt ni respuesta: solo contadores, costo, latencia, contexto, efector y hash.
- No escribe en la BD durante el request; a 38 M llamadas/mes una fila por llamada no escala.
- Un job nocturno agrega el archivo en `ai_usage_daily` (`fecha × contexto × efector`).
- El agregado es idempotente: borra las filas del día y las reinserta desde el archivo.

Comandos:

```bash
php yii ai-usage/aggregate                  # ayer
php yii ai-usage/aggregate --date=2026-11-12
php yii ai-usage/aggregate --all
php yii ai-usage/report --from=2026-11-01 --to=2026-11-30 --group=context
php yii ai-usage/report --group=efector
php yii ai-usage/prune --days=30
php yii ai-usage/status
```

Activación en `frontend/config/params.php` y `console/config/params.php`:

```php
'ia_usage_tracking_habilitado' => true,
'ai_usage_persist_habilitado' => true,
'ai_usage_log_dir' => '@common/runtime/ai-usage',
```

`ai_usage_persist_habilitado = false` apaga solo la persistencia; el tracker en memoria sigue.

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
| Persistencia de métricas (BD, Prometheus) | **Implementada**: JSONL por llamada + agregado diario `ai_usage_daily` (`ai-usage/aggregate`) |
| Dashboard por efector | `ai_usage_daily` guarda `id_efector`; falta la vista en admin |
| Alertas de umbral | Pendiente (ver § gobernanza en [estrategias-api.md](estrategias-api.md)) |
| Métricas STT (minutos servidor, fallback, calidad device vs server) | Pendiente; definición en [stt.md § Monitoreo](stt.md#monitoreo-pendiente-de-implementación) |

## Gobernanza

- Revisar precios Vertex cada 6–12 meses.
- Cuotas por institución cuando exista facturación interna.
- Comparar `ratio_input_en_cache` con el supuesto **~25 %** (favorable) de [costos-api.md](../costos-api.md); COGS base usa columna **sin caché**.
