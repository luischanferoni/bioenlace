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

## Intents (ejemplo profesionales)

- Resumen: `metric_id=profesionales_conteo_efector` → `/api/info`
- Listado: `metric_id=profesionales_listado_efector` → `/api/listar`

## Extender

1. Métrica + bloques `query`, `output` y `presentation_handler` en YAML.
2. Handler en `MetricPresentationRegistry` + clase en `Organization/Presentation/` (o dominio correspondiente).
3. Intent con `rbac_route` `/api/info` o `/api/listar` y `metric_id` en `open_ui.params`.

## Admin backend

Superadmin: menú **Consultas staff** → grants BD y catálogo YAML.
