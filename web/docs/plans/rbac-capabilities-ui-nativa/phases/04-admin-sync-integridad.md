# Fase 4 — Admin, sync e integridad CI

## Objetivo

Operabilidad: asignar capabilities en admin, sync en deploy, CI que impida regresiones.

## Tareas

### 4.1 Permission Catalog (admin)

- [x] Extender `PermissionCatalogController`:
  - listado capabilities (pestaña «Capabilities UI nativa»)
  - detalle capability: rutas, roles, intents relacionados
  - editar roles por capability (paridad con intents)
- [x] Catálogo admin incluye capabilities sin confundir con intents NL.

### 4.2 CLI y deploy

- [x] Documentar orden staging/prod (ver README del plan y migraciones).
- [ ] Pipeline CI: `catalog-integrity/check` tras sync en job de metadata.

### 4.3 Reglas integridad

Ampliar `catalog-integrity/check`:

- [ ] Ruta API staff sin padre intent/capability → **error**
- [x] Capability assignable sin rutas → **error**
- [x] Ruta guardia ingreso/triage/egreso solo bajo `listado_pacientes` → **warning**
- [x] Grant `front_ver_historial_paciente` sin capability `encounter.ver_como_staff` → **warning** hasta Fase 6

### 4.4 Sesión / cache

- [x] `BioenlaceRbacRevision::bump()` en `PermissionRolesAssignmentService` tras cambios.
- [ ] Documentar re-login tras deploy RBAC en checklist staging.

## Criterios de aceptación

- [x] Admin asigna `guardia.ingreso` a rol custom sin SQL manual.
- [ ] CI falla si alguien agrega acción API sin enlazar catálogo.

## PR sugerido

`feat(admin): permission catalog capabilities + integrity rules`
