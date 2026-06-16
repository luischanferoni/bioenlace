# Fase 6 — Retiro legacy y documentación estable

## Objetivo

Eliminar código y datos del modelo atributo×rol; dejar documentación estable; borrar carpeta del plan.

## Tareas

### 6.1 Código a eliminar o vaciar

| Componente | Acción |
|------------|--------|
| `AttributePermissionEvaluator` | `@deprecated`; eliminar cuando no queden referencias runtime |
| `AttributePermissionKeyMapper` | `@deprecated` |
| `EditSurfaceAuthorizationService` (grants) | Intent-only si `DataAccessGenericChannelRetirement` |
| `QueryAuthorizationService` (required_groups RBAC) | Intent path prioritario; legacy convivencia |
| `PermissionCatalogService::listAttributes` | Solo integridad / prune |
| Vistas `edit-attribute-roles` | Ya fuera del admin |
| Intents YAML `data-access.editar`, `data-access.listar`, `data-access.info` | Mantener para open_ui hasta retiro endpoints |
| `DataAccessCatalogIntentSupport` para genéricos | Desactivado si canales retirados |

### 6.2 Base de datos / RBAC

- [x] `catalog-permission/prune-attributes` (dry-run + `--execute=1`)
- [ ] Ejecutar prune en staging/producción tras backup
- [x] Integridad: grants atributo → **error**

### 6.3 `data-access-config`

- [x] Entidades migradas marcadas con `migrated_intent_id(s)`
- [ ] Evaluar retiro YAML por entidad cuando endpoints legacy muertos

### 6.4 `domain-operation-policies.yaml`

- [ ] Eliminar `DataAccess.edit|list|info` cuando `/api/info|listar|editar` no usen bridge genérico

### 6.5 Integridad

- [x] Grants atributo en `auth_item` promovidos a error

### 6.6 Documentación

- [x] `web/docs/arquitectura/rbac-catalogo-permisos.md` actualizado
- [x] ADR `web/docs/decisions/autorizacion-solo-por-intents.md`
- [x] `common/components/Platform/Core/DataAccess/README.md`

### 6.7 Cierre plan

- [ ] Checklist overview «criterios de éxito» en staging
- [ ] Borrar `web/docs/plans/rbac-intents-unificado/`
- [ ] Quitar fila de `plans/README.md`

## Entregables

- [x] Herramienta prune + integridad estricta
- [x] Doc estable publicada
- [ ] Sin referencias runtime a permisos atributo (pendiente eliminar clases)
- [ ] Carpeta plan eliminada (tras validación staging)

## Estado

En progreso — documentación y prune cableados; ejecución BD staging y borrado código legacy pendientes.
