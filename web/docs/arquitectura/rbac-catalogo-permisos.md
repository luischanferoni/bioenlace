# RBAC y catálogo de permisos (greenfield)

## Principios

- **BioenlaceDbManager** se conserva: PES → rol vía `servicios.item_name`.
- **Permisos assignables** en admin:
  - **Intents** — operaciones / flows (`create/`, `read/`, `update/`, `delete/`).
  - **Atributos** — `Entidad.atributo.read|info|edit` declarados en `data-access-config`.
- **Grupos** en data-access-config: solo presentación (chips/bloques); el grant es por **atributo**.
- **Edit complejo**: solo intent (BD → AR → YAML intent); no duplicar en `edit` disperso.
- **open_ui intermedio**: hereda permiso del intent padre (`FlowStepAccessService` + header opcional `X-Flow-Intent-Id`).

## Fuentes de verdad

| Canal | Cadena |
|-------|--------|
| Create / update / delete complejo | BD → AR → `schemas/intents/{create,update,delete}/` |
| Read / info por atributo | BD → AR → `data-access-config` |
| Edit escalar | BD → AR → `data-access-config` (`edit.attributes`) |
| Pasos UI dentro de flow | Derivados del intent (sin grant propio) |

## Herramientas

- **Admin**: `/admin/permission-catalog/index`, `/admin/permission-catalog/integrity`
- **CLI**: `php yii catalog-integrity/check`

## Migración gradual

1. Campo opcional `permission:` en intents (nombre lógico, p. ej. `ProfesionalEfectorServicio.create`).
2. Mover YAML a subcarpetas CRUD (warnings de integridad mientras queden en raíz).
3. Bloque `attributes:` por entidad en data-access-config (grupos opcionales).
4. Migrar grants `data_access_role_grant` → `auth_item` (`catalog-permission/migrate-grants` o sync completo).

## Fase 3 — sync auth_item + asignación por rol

- CLI: `php yii catalog-permission/sync` — registra permisos lógicos, enlaza rutas legacy y migra grants de atributos.
- CLI (solo grants): `php yii catalog-permission/migrate-grants`
- Opción `--deactivateLegacyGrants=1` — desactiva filas en `data_access_role_grant` tras migrar.
- Migraciones: `m260621_100000_catalog_logical_permissions_rbac`, `m260622_100000_migrate_data_access_grants_to_auth_item`.
- Admin: `/admin/permission-catalog/roles` — sync + migración; `/admin/permission-catalog/edit-role?role=…`.

Jerarquía webvimark compatible con `AllowedRoutesResolver`:

```
rol → Turno.create (type 2) → /api/turnos/crear-como-paciente (type 3)
```

## Fase 4 — grants de atributos → auth_item

- **`AttributePermissionKeyMapper`**: grupo legacy (`Persona.identidad`) + operación DataAccess (`filter|read|aggregate|write`) → claves `Entidad.atributo.read|info|edit`.
- **`DataAccessGrantMigratorService`**: copia `data_access_role_grant` activos a `auth_item` + `auth_item_child` (rol → permiso atómico).
- **`AttributePermissionEvaluator`**: evalúa primero vía `auth_item`; fallback a grants legacy en BD.
- **`syncAll()`** en `CatalogPermissionSyncService`: sync de catálogo + migración de grants en un paso.
- Admin legacy (`/admin/data-access-grant`) muestra banner hacia el nuevo panel; deprecar cuando la matriz cubra todos los roles.

### Mapeo operaciones

| DataAccess | Permiso catálogo |
|------------|------------------|
| `filter`, `read` | `.read` |
| `aggregate` | `.info` |
| `write` | `.edit` |
