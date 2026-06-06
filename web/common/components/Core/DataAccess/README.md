# Core / DataAccess

Permisos por **grupos de atributos**, **scope checkers** y **métricas** staff para consultas API y asistente.

## Archivos clave

- `metadata/attribute_groups_v1.yaml` — grupos, métricas, grants YAML, plan query, `presentation_handler`
- `DataAccessUiService` — `/api/info` y `/api/listar` con ui_json genérico
- `Presentation/MetricPresentationRegistry` — handlers de presentación por métrica
- `QueryCompiler` / `MetricQueryExecutor` — compilación y ejecución

## API staff (genérica)

| Ruta RBAC | HTTP | Uso |
|-----------|------|-----|
| `/api/info` | `GET|POST /api/v1/info` | Métricas aggregate/grouped + ui_json mensaje |
| `/api/listar` | `GET|POST /api/v1/listar` | Métricas rows + ui_json listado |

Parámetros comunes: `metric_id` (requerido), filtros allowlisted por métrica.

## Intents (asistente)

Dos intents YAML genéricos (no uno por métrica):

| Intent | HTTP | Uso |
|--------|------|-----|
| `data-access.info` | `/api/info` | Métricas aggregate/grouped |
| `data-access.listar` | `/api/listar` | Métricas rows |

El `metric_id` y filtros se resuelven en runtime (`DataAccessMetricDiscoveryService`, `DataAccessFlowDraftHydrator`) desde `metrics.*.assistant.keywords` en este YAML — **sin valores de atributos** (sexo, especialidad concreta, etc.; eso va en `filter_synonyms` / resolvers).

## Extender

1. Métrica + bloques `query`, `output`, `presentation_handler` y `assistant.keywords` en YAML.
2. Handler en `MetricPresentationRegistry` + clase en dominio correspondiente.
3. No crear intents YAML por métrica: reutilizar `data-access.info` o `data-access.listar`.

## Admin backend

Superadmin: menú **Consultas staff** → grants BD y catálogo YAML.
