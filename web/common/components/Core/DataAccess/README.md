# Core / DataAccess

Permisos por **grupos de atributos**, **scope checkers** y **métricas** staff para consultas API y asistente.

## Archivos clave

- `Assistant/SubIntentEngine/schemas/data-access-config/` — grupos por entidad, métricas, edición dispersa, `filter_synonyms`
- `data_access_role_grant` (BD) — permisos por rol + grupo (fuente única de grants)
- `data_access_attribute_field` (BD) — esquema de campos editables por grupo (tipos, widgets, options)
- `DataAccessUiService` — `/api/info` y `/api/listar` con ui_json genérico
- `Presentation/MetricPresentationRegistry` — handlers de presentación por métrica
- `QueryCompiler` / `MetricQueryExecutor` — compilación y ejecución

## API staff (genérica)

| Ruta RBAC | HTTP | Uso |
|-----------|------|-----|
| `/api/info` | `GET|POST /api/v1/info` | Métricas aggregate/grouped + ui_json mensaje |
| `/api/listar` | `GET|POST /api/v1/listar` | Métricas rows + ui_json listado |
| `/api/editar` | `GET|POST /api/v1/editar` | Superficie → sujeto → aspectos → formulario → confirmación → `step=apply` (mutación) |

Parámetros comunes: `metric_id` (requerido), filtros allowlisted por métrica.

## Intents (asistente)

Dos intents YAML genéricos (no uno por métrica):

| Intent | HTTP | Uso |
|--------|------|-----|
| `data-access.info` | `/api/info` | Métricas aggregate/grouped (catálogo PHP, sin YAML flow) |
| `data-access.listar` | `/api/listar` | Métricas rows (catálogo PHP, sin YAML flow) |
| `data-access.editar` | `/api/editar` | Edición dispersa (catálogo PHP, sin YAML flow) |

El `metric_id` / `surface_id` y filtros se resuelven en runtime (`DataAccessMetricDiscoveryService`, `DataAccessEditDiscoveryService`, hydrators) desde keywords en **data-access-config** — **sin valores de atributos** (sexo, especialidad concreta, etc.; eso va en `filter_synonyms` / resolvers).

Edición: corte temprano si el rol no tiene ningún aspecto con `write` (`EditSurfaceAuthorizationService`). El intent `data-access.editar` se oculta del catálogo si no hay superficies editables. Preprocess: `ChatPreprocessService::isStaffDataAccessEditQuery`. Mutación vía `MutationExecutor` + handlers por grupo (`EditMutationRegistry`); aspectos `open_ui` devuelven `open_ui` en la respuesta.

Auditoría mutaciones: canal `data-access`, evento `data_access_edit_applied` (`EditMutationAuditLogger`).

Migración agenda staff: `profesional-agenda.editar-flow` deprecado (`catalog_exclude`); usar `data-access.editar` → aspecto `agenda_horarios` → `profesional-agenda.configurar-agenda`.

## Extender

1. Grupo en `{Entidad}.yaml`; métrica con bloques `query`, `output`, `presentation_handler` y `keywords`.
2. Handler en `MetricPresentationRegistry` + clase en dominio correspondiente.
3. Grant en BD (admin **Permisos por atributo**).
4. No crear intents YAML por métrica: reutilizar `data-access.info` o `data-access.listar`.

## Admin backend

Superadmin: menú **Consultas staff** → grants BD y catálogo YAML. La pantalla de grants avisa si hay roles huérfanos (eliminados de webvimark).
