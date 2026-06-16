# Fase 6 — Retiro legacy y documentación estable

## Objetivo

Eliminar código y datos del modelo atributo×rol; dejar documentación estable; borrar carpeta del plan.

## Tareas

### 6.1 Código a eliminar o vaciar

| Componente | Acción |
|------------|--------|
| `AttributePermissionEvaluator` | Eliminar |
| `AttributePermissionKeyMapper` | Eliminar |
| `EditSurfaceAuthorizationService` (grants) | Eliminar o reducir a helpers UI |
| `QueryAuthorizationService` (required_groups RBAC) | Eliminar o solo dominio |
| `PermissionCatalogService::listAttributes` assignables | Eliminar |
| Vistas `edit-attribute-roles` | Eliminar |
| Intents YAML `data-access.editar`, `data-access.listar`, `data-access.info` | Eliminar |
| `DataAccessCatalogIntentSupport` para genéricos | Eliminar |

### 6.2 Base de datos / RBAC

- Migración Yii: borrar `auth_item` donde `name` match `%.read|info|edit` patrón atributo (con backup)
- Limpiar `auth_item_child` huérfanos
- `catalog-permission/sync --prune-attributes` si se implementó

### 6.3 `data-access-config`

- Por entidad migrada: eliminar YAML o marcar legacy
- Evaluar si `data_access_attribute_field` (BD) sigue necesaria o se deriva todo del YAML intent
- Actualizar `php yii data-access-catalog/check` o retirar comando

### 6.4 `domain-operation-policies.yaml`

- Eliminar entradas `DataAccess.edit|list|info` si canal genérico muerto
- Revisar duplicados staff/own ya cubiertos por intents

### 6.5 Integridad

- Promover warnings → errors para grants atributo
- 0 errores en CI

### 6.6 Documentación

- Reescribir `web/docs/arquitectura/rbac-catalogo-permisos.md`
- ADR en `web/docs/decisions/` (ej. `autorizacion-solo-por-intents.md`)
- Actualizar referencias en `common/components/Platform/Core/DataAccess/README.md`
- **No** enlazar a `plans/` desde docs estables

### 6.7 Cierre plan

- Checklist overview «criterios de éxito»
- Borrar `web/docs/plans/rbac-intents-unificado/`
- Quitar fila de `plans/README.md`

## Entregables

- [ ] Sin referencias runtime a permisos atributo
- [ ] Migración BD ejecutada en staging/producción según runbook
- [ ] Doc estable publicada
- [ ] Carpeta plan eliminada

## Dependencias

- Fases 3–5 completas para dominios críticos
- Ventana de convivencia cumplida

## Rollback

- Restaurar backup `auth_item` / `auth_assignment`
- Revertir migración BD en entorno de prueba antes de producción (misma nota que `rbac-catalogo-permisos.md`)

## Estado

Pendiente.
