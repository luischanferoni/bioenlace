# Fase 6 — Migración legacy `front_*` y cierre

## Objetivo

Reducir permisos legacy duplicados; publicar documentación estable; retirar plan.

## Tareas

### 6.1 Mapa migración

- [x] Ampliar `intent-grant-migration-map.yaml` con entradas `front_*` → capability:
  - `front_ver_historial_paciente` → `encounter.ver_como_staff`
  - `analisis` → `encounter.capturar`
- [x] `IntentGrantMigrationService` soporta `capability_grant_sources`
- [x] Migración `m260814_140000_encounter_legacy_capability_grants`
- [ ] Validar en staging con `php yii catalog-permission/migrate-grants` (idempotente)

### 6.2 Deprecación

- [x] `legacy-permission-aliases.yaml` + bloque deprecados en admin catálogo
- [ ] Retirar grants legacy de roles tras QA (sin `prune` hasta backup)
- [ ] No ejecutar `prune` hasta backup + validación QA.

### 6.3 Documentación estable

- [x] ADR `decisions/autorizacion-capabilities-ui-nativa.md`
- [x] Actualizar `arquitectura/rbac-catalogo-permisos.md` (assignables = intents + capabilities)
- [x] Actualizar `producto/urgencias-guardia.md` sección permisos
- [x] Entrada en `his-completo/02-urgencias.md` checklist autorización

### 6.4 Cierre plan

- [ ] QA regresión completa (matriz Fase 0)
- [ ] Eliminar `plans/rbac-capabilities-ui-nativa/`
- [ ] Quitar fila de [plans/README.md](../README.md) «Planes activos»

## Criterios de aceptación

- [ ] Cero grants activos solo-legacy para operaciones migradas (integridad warning → error).
- [ ] Equipo puede operar RBAC solo desde admin + YAML capabilities.

## PR sugerido

`docs(rbac): ADR capabilities + migrate front grants + close plan`
