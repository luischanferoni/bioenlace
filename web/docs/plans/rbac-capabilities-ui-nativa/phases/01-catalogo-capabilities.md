# Fase 1 — Catálogo capabilities (metadata + registro)

## Objetivo

Introducir capabilities como assignables sin romper intents: YAML, sync a `auth_item`, índice en runtime.

## Tareas

### 1.1 Metadata

- [ ] Crear `web/common/metadata/bioenlace/permission/capabilities/guardia.yaml`
- [ ] Crear `…/encounter.yaml`
- [ ] Crear `…/panel.yaml`
- [ ] Esquema documentado en `design.md` del plan (capability_id, routes, default_roles, ui_surfaces)

### 1.2 Motor de sync

- [ ] `CapabilityManifestIndex` (lectura YAML, cache)
- [ ] `CapabilityPermissionSyncService` o extensión de `CatalogPermissionSyncService`:
  - registrar capability en `auth_item` (type 2)
  - enlazar capability → rutas (type 3)
  - **no** borrar intents existentes
- [ ] Comando CLI: `php yii catalog-permission/sync-capabilities`
- [ ] Invocar sync-capabilities desde `catalog-permission/sync` (opcional flag `--capabilities-only`)

### 1.3 Índice runtime

- [ ] `CapabilityAccessService::userCanExecuteCapability($userId, $capabilityId)` — paralelo a `IntentAccessService`
- [ ] Tests unitarios: index carga rutas, sync idempotente

## Fuera de esta fase

- Grants por rol en producción (Fase 2 migraciones).
- Admin UI (Fase 4).

## Criterios de aceptación

- [ ] `sync-capabilities` en staging crea items sin duplicar rutas.
- [ ] `catalog-integrity/check` ampliado (mínimo: capabilities sin rutas = error).
- [ ] Cero cambio de comportamiento runtime hasta Fase 2 (solo infra).

## PR sugerido

`feat(rbac): capability manifest + sync-capabilities CLI`
