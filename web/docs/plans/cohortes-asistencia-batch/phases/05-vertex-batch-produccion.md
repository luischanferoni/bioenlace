# Fase 5 — Vertex batch en producción

**Estado:** implementada (código + guía ops). Activación en GCP es manual por entorno.

## Qué se entrega en código

| Componente | Rol |
|------------|-----|
| `CarePackVertexBatchSubmitter` | Agrupa jobs → JSONL → GCS → `batchPredictionJobs` |
| `CarePackVertexBatchPoller` | Poll estado Vertex → materializa `care_cohort_pack` |
| `CarePackVertexBatchTelemetry` | `AICostTracker` contexto **`care-pack-vertex-batch`** |
| `CarePackConfig::vertexBatchReadiness()` | Chequeo bucket, credenciales, flags |
| `php yii care-pack/vertex-status` | Diagnóstico ops |
| `max_wait_minutes` | Flush de lote aunque `pending < min_jobs_for_vertex` |

## Infra GCP (ops)

### 1. Bucket GCS dedicado

Ejemplo: `bioenlace-care-batch-prod` (región coherente con Vertex, p. ej. `us-central1`).

Prefijos sugeridos (ya en `params.php`):

- `care-batch/input/` — JSONL de entrada
- `care-batch/output/` — salida Vertex

Lifecycle opcional: borrar objetos > 30 días.

### 2. IAM cuenta de servicio

La misma cuenta que usa `GoogleAuth` / Vertex en la app necesita:

| Rol | Uso |
|-----|-----|
| `roles/storage.objectAdmin` (bucket dedicado) | Subir input, leer output JSONL |
| `roles/aiplatform.user` | Crear y consultar `batchPredictionJobs` |

Principio de mínimo privilegio: limitar `objectAdmin` al bucket de care-batch, no al proyecto entero.

### 3. Params producción (`params-local.php`)

```php
'care_cohort' => [
    'enabled' => true,
    'vertex_batch' => [
        'enabled' => true,
        'gcs_bucket' => 'bioenlace-care-batch-prod',
        'min_jobs_for_vertex' => 10,   // calibrar con volumen
        'max_wait_minutes' => 120,     // no dejar jobs colgados de noche
    ],
],
```

Verificar antes de activar:

```bash
php yii care-pack/vertex-status
```

## Cron producción

```bash
# Cada 5 min: submit batch + poll + jobs sync residual
php yii care-pack/run-jobs

# Cada 15 min (refuerzo poll si run-jobs no alcanza)
php yii care-pack/poll-vertex
```

Con `vertex_batch.enabled = true`, los jobs nuevos se encolan en modo `vertex_batch`. Si Vertex falla 5 veces, el job vuelve a `sync`.

## Telemetría de costos

- **Sync** (fallback / dev): contextos `care-pack-assistance-batch`, `care-pack-followup-batch`, `care-pack-education-batch` vía `IAManager`.
- **Vertex batch**: contexto agregado **`care-pack-vertex-batch`** al completar cada línea en el poller (`usageMetadata` si Vertex lo incluye).

Calibrar con `ia_usage_tracking_habilitado = true` y revisar `AICostTracker::getResumen()['por_contexto']`.

## Calibración `min_jobs_for_vertex`

| Volumen diario (cohortes nuevas) | Sugerencia |
|----------------------------------|------------|
| &lt; 20 | `min_jobs = 5`, `max_wait = 60` |
| 20–100 | `min_jobs = 10`, `max_wait = 120` |
| &gt; 100 | `min_jobs = 20`, `max_wait = 30` |

Objetivo: amortizar costo fijo del batch job sin retrasar packs &gt; 2 h en horario laboral.

## Rollback

1. `care_cohort.vertex_batch.enabled = false` — jobs nuevos van a sync.
2. Jobs `vertex_submitted` siguen en poll hasta completar o fallar.
3. No borrar bucket hasta drenar cola.
