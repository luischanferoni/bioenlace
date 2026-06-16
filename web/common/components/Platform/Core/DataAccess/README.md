# Core / DataAccess

Motor de **métricas** y **edición dispersa** staff detrás de intents concretos. La autorización de producto es por **`intent_id`**; este módulo ejecuta consultas y UI JSON.

## Estado (post-migración intents)

| Antes | Ahora |
|-------|--------|
| Asistente sugería `data-access.info\|listar\|editar` | Intents por dominio (`profesionales.conteo-efector`, `profesional-agenda.configurar-staff`, …) |
| RBAC `Entidad.atributo.read\|info\|edit` | RBAC `intent_id`; grants atributo legacy en retiro |
| Descubrimiento NL desde `data-access-config` | `IntentMetricIndex` / `IntentEditSurfaceIndex`; genéricos fuera del catálogo si hay paridad |

Los endpoints `/api/info`, `/api/listar`, `/api/editar` siguen como **transporte HTTP** para `open_ui` de intents (`action_id: data-access.info`, etc.) hasta retiro final del código.

## Archivos clave

- `schemas/data-access-config/` — definición de métricas, superficies edit, grupos (presentación; no grants)
- `DataAccessUiService`, `DataAccessEditUiService` — respuestas ui_json
- `QueryAuthorizationService` — métricas: intent enlazado → RBAC intent; legacy → grants atributo (`@deprecated`)
- `EditSurfaceAuthorizationService` — superficies: intent enlazado → RBAC intent
- `DataAccessGenericChannelRetirement` — catálogo NL sin `data-access.*` cuando todo migró
- `IntentMetricIndex`, `IntentEditSurfaceIndex` — enlaces declarativos YAML ↔ métrica/superficie

## Extender un dominio staff

1. Crear intent(s) YAML con `metric_id` o `edit_surface_id`, `domain_operation`, `fields` si aplica.
2. Marcar `migrated_intent_id(s)` en la entrada de `data-access-config` (convivencia).
3. Entrada en `intent-grant-migration-map.yaml` si hay grants legacy que copiar.
4. `php yii catalog-permission/sync` + `migrate-grants`.
5. **No** crear nuevos intents genéricos `data-access.*`.

## Admin e integridad

- Permisos: **solo intents** en `/admin/permission-catalog`.
- `php yii catalog-integrity/check` — errores si quedan grants `Entidad.atributo.*` en `auth_item`.

## Referencia

- [rbac-catalogo-permisos.md](../../../../docs/arquitectura/rbac-catalogo-permisos.md)
- [autorizacion-solo-por-intents.md](../../../../docs/decisions/autorizacion-solo-por-intents.md)
