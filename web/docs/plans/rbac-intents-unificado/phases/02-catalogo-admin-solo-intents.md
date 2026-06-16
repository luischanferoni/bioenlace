# Fase 2 — Catálogo admin solo intents

## Objetivo

Simplificar el panel de permisos: asignación **rol ↔ intent** únicamente; vista informativa de campos/grupos parseados del YAML.

## Tareas

### 2.1 `PermissionCatalogService`

- `listIntents()`: sin cambio funcional principal
- `listAttributes()` / grants assignables: **deprecar** para admin; sustituir por `listIntentFieldManifest(intent_id)` solo lectura
- `findPermissionRow`: solo intents (rechazar keys `Entidad.atributo.*` en UI)

### 2.2 Vistas admin

- `permission-catalog/index`: quitar filas `kind: attribute` y acciones «editar roles de atributo»
- Nueva vista o panel expandible: detalle intent → field_groups, fields, rbac_route, domain_operation, open_ui steps
- `rbac-role/update`: solo checklist intents (ya parcialmente así); quitar referencias a atributos assignables
- Actualizar breadcrumbs y redirects legacy (`DataAccessCatalogController`, etc.)

### 2.3 `catalog-permission/sync`

- Dejar de crear/actualizar ítems `Entidad.atributo.read|info|edit`
- Opción `--prune-attributes` (fase 6) documentada pero no obligatoria aún

### 2.4 `PermissionRolesAssignmentService`

- Validar que solo se asignen keys de intent existentes en manifiesto

### 2.5 Menú admin

- «Catálogo de permisos» → subtítulo o ayuda: «Los campos se definen en el YAML de cada intent»
- Eliminar enlaces a gestión de grants por atributo

## Entregables

- [ ] Admin usable solo con intents
- [ ] Vista lectura campos desde YAML
- [ ] Sync no genera nuevos permisos atributo
- [ ] Checklist manual: asignar rol piloto sin pantalla de atributos

## Dependencias

- Fase 1 (contrato YAML para parsear campos)

## Riesgos

- Operadores acostumbrados a matriz atributo: comunicar cambio; export CSV de intents por rol si hace falta

## Estado

Pendiente.
