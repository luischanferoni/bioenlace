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
4. Migrar grants `data_access_role_grant` → `auth_item` (`m260622`; tabla eliminada en `m260627`).

## Fase 3 — sync auth_item + asignación por rol

- CLI: `php yii catalog-permission/sync` — registra permisos lógicos y enlaza rutas API.
- La herencia rol → permiso lógico (`inheritRoleGrantsFromRoute`) sube la jerarquía `auth_item_child` (rol → permiso → ruta), no solo padres directos de la ruta.
- Rutas ghost (internación, UI clínica): `RbacRouteGhostInheritanceService` propaga **rol → ruta hija** desde roles con acceso a la ruta padre; no copiar permisos lógicos como padres de rutas downstream.
- Migraciones: `m260621_*`, `m260622_*`, `m260626_*`, `m260627_*`, `m260628_*`, `m260629_*`.
- Admin: `/admin/permission-catalog/roles` — sync; `/admin/permission-catalog/edit-role?role=…`.

Jerarquía webvimark compatible con `AllowedRoutesResolver`:

```
rol → Turno.create (type 2) → /api/turnos/crear-como-paciente (type 3)
```

## Fase 4 — permisos atómicos de atributos

- **`AttributePermissionKeyMapper`**: grupo (`Persona.identidad`) + operación DataAccess (`filter|read|aggregate|write`) → claves `Entidad.atributo.read|info|edit`.
- **`AttributePermissionEvaluator`**: evalúa vía `auth_item` + scope desde YAML (`data-access-config`).

### Políticas de dominio por operación

Capa **después del RBAC** (ruta / `auth_item`) y **antes de reglas de negocio** (ventanas de cancelación, etc.):

```
RBAC (¿puede intentar Entidad.operacion?) → DomainOperationAuthorizer (¿sobre ESTE recurso?) → servicio de dominio
```

- **Metadata:** `schemas/domain-operation-policies.yaml` — mapeo `Turno.cancel` → handlers (`any_of` / `policies`); clave `domain_only_operations` lista operaciones ABAC internas sin permiso assignable en `auth_item`.
- **Fail-closed:** toda clave pasada a `DomainOperationAuthorizer::assert()` debe existir en el YAML; si falta, `DomainOperationForbiddenException`.
- **Registry:** `DomainOperationPolicyRegistry` — handler_id → clase PHP (estable, sin reglas).
- **Implementaciones:** `Scheduling/Service/Authorization/*`, `Organization/Service/Authorization/*`, `Clinical/Service/Authorization/*`, `Clinical/Inpatient/Service/Authorization/*`.
- **API:** `ApiDomainOperationBridge`, `EfectorDomainAccessService`, `EncounterDomainAccessService`; `ClinicalAccessTrait` en controllers clínicos.
- **Servicios transversales:** `EfectorDomainAccessService`, `EncounterDomainAccessService`; `ProfesionalEfectorServicioDomainAuthorizationService` (PES).
- **Primitivo efector:** `OrganizationEfectorAccess` solo en políticas/handlers; no en controllers.
- `GuardiaEfectorAccess` / `InternacionEfectorAccess`: solo utilidades de dominio (PES, camas, pertenencia geográfica).

`scope_checker` (DataAccess) sigue siendo ABAC del canal métricas/edición dispersa; las políticas de dominio generalizan el mismo concepto para operaciones del catálogo RBAC.

### Mapeo operaciones

| DataAccess | Permiso catálogo |
|------------|------------------|
| `filter`, `read` | `.read` |
| `aggregate` | `.info` |
| `write` | `.edit` |

## Fase 5 — catálogo completo y cierre legacy

### Intents

- Todos los YAML en `schemas/intents/{create,read,update,delete}/` declaran `permission:` explícito.
- Stubs catalog-only: `read/data-access.info.yaml`, `read/data-access.listar.yaml`, `update/data-access.editar.yaml` (sincronizables a `auth_item`).
- `IntentPermissionResolver`: mapeo explícito con y sin sufijo `-flow` (p. ej. `mapa-camas` → `view_map`).

### Atributos

- `Persona.yaml`, `Turno.yaml`, `ProfesionalEfectorServicio.yaml`, `ProfesionalEfectorServicioAgenda.yaml` con bloque `attributes:` + `groups:` (presentación + `scope_checker`).
- Grants atómicos vía `catalog-permission/sync` → `auth_item` + `auth_item_child`.

### Scope ABAC post-migración

- `AttributeGroupCatalog::getEntityGroupScopeChecker()` — lee `groups.scope_checker` o `edit.scope_checker` del YAML.
- `AttributePermissionEvaluator` — solo `auth_item` (permisos atómicos) + scope desde YAML.

### Cierre legacy (m260626–m260629)

- Elimina permisos huérfanos `Internacion.update` / `Internacion.view` (duplicados de `discharge` / `view_map`).
- Elimina tabla `data_access_role_grant`.
- `m260628`: rutas agenda `editar-*` y `efectores/elegir*`.
- `m260629`: `pes/elegir`, `servicios/elegir`, notificaciones `*-como-paciente` + re-enlaces RBAC.
- Admin `/admin/data-access-grant` retirado; asignación en **Catálogo de permisos → Roles**.

### CLI

- `php yii catalog-permission/seed-permissions` — re-aplica `permission:` inferido a intents nuevos.
- `php yii catalog-integrity/check` — integridad catálogo (0 errores esperado).
