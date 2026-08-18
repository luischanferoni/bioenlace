# RBAC y catálogo de permisos

Documentación estable del modelo de autorización Bioenlace: motor Yii, **permisos por `intent_id` y capabilities UI nativa**, catálogo unificado en admin, identidad sin webvimark.

## Principios

- **Motor RBAC:** `BioenlaceDbManager` (Yii) + caché en sesión (`BioenlaceSessionPermissions`) y chequeos (`BioenlaceAccessChecker`).
- **PES → rol:** se conserva la resolución vía `servicios.item_name`.
- **Permisos assignables** en admin:
  - **Intents** — clave = `intent_id` del YAML (asistente, flows NL, métricas staff).
  - **Capabilities UI nativa** — clave = `capability_id` (`guardia.*`, `encounter.*`, `panel.*`) para web/móvil sin intent en cada request.
- **Atributos `Entidad.atributo.*`:** legacy; no se asignan en admin; eliminar de `auth_item` tras migración (ver ADR [autorizacion-solo-por-intents.md](../decisions/autorizacion-solo-por-intents.md)).
- **Permisos operativos legacy** (`analisis`, `front_ver_historial_paciente`): deprecados; migrar grants a capabilities (ver ADR [autorizacion-capabilities-ui-nativa.md](../decisions/autorizacion-capabilities-ui-nativa.md)).
- **Web staff (SPA):** `frontend/controllers` exigen autenticación; el RBAC real vive en **API v1** (`BioenlaceApiAccessControl`).
- **Admin:** RBAC por ruta (`BioenlaceAdminAccessControl`).

## Capas

| Capa | Componentes | Responsabilidad |
|------|-------------|-----------------|
| API v1 | `BioenlaceApiAccessControl`, `ApiRoutePermissionResolver` | `403` si falta permiso de ruta (intent o capability padre) |
| Web SPA | `FrontendAuthenticatedAccessControl`, `EnforceGhostAccessBootstrap` | Solo login; sin enumerar intents/capabilities en controllers |
| Sesión | `BioenlaceSessionPermissions`, `BioenlaceRbacRevision` | Pobla permisos tras login; revisión global invalida caché tras cambios RBAC |
| Permiso intent | `IntentPermissionResolver`, `IntentAccessService` | Clave = `intent_id`; atajos y ejecución asistente |
| Capability UI | `CapabilityManifestIndex`, `CapabilityAccessService`, `CapabilityPermissionSyncService` | Clave = `capability_id`; UIs nativas + rutas API enlazadas |
| Flow step | `FlowStepAccessService`, header `X-Flow-Intent-Id` | Pasos `open_ui` heredan intent padre |
| Dominio recurso | `DomainOperationAuthorizer`, políticas en `domain-operation-policies.yaml` | ¿Sobre **este** PES/turno/encounter/efector? |
| Manifiesto UX | `home_panel_manifest.yaml`, `GuardiaBoardCapabilityService` | Visibilidad CTAs (complementa RBAC; no lo sustituye) |
| Admin catálogo | `PermissionCatalogController` | Intents, capabilities, integridad, roles |
| Identidad | `common\models\User`, `AuthController` | Login, contraseña, confirmación e-mail |

### Jerarquía RBAC (ejemplos)

```
rol → guardia.ingreso (type 2) → /api/clinical/emergency-guardia/ingresar (type 3)
rol → condicion-laboral.editar-propio (type 2) → /api/profesional-efector-servicio/editar-condicion-laboral (type 3)
rol → encounter.capturar (type 2) → /api/clinical/encounter/captura-guardar (type 3)
```

## Fuentes de verdad

| Canal | Cadena |
|-------|--------|
| Operaciones asistente | `assistant/intents/{create,read,update,delete}/` (métricas en `read/`; pantallas en `read/flows/`) + `intent-families.yaml` |
| UI nativa guardia / encounter / panel | `permission/capabilities/*.yaml` + sync a `auth_item` |
| CTAs tablero EMER | `ui/home_panel_manifest.yaml` (`capability_id`, exclusiones UX por rol) |
| Staff métricas / edición (migrado) | Intent con `metric_id` o `edit_surface_id` |
| Pasos UI dentro de flow | Derivados del intent; `FlowStepAccessService` + `X-Flow-Intent-Id` |
| Listado NL / IA | `IntentAccessService::userCanExecuteIntent` vía catálogo intents |
| Atajos inicio | `IntentAccessService::userHasIntentGrant` — solo intents (no pantallas nativas); UI genérica embebida en el asistente |
| Campos editables | `fields` / `field_groups` en YAML del intent |
| Migración legacy → capability | `intent-grant-migration-map.yaml` (`capability_grant_sources`) |
| Alias deprecados | `legacy-permission-aliases.yaml` |

Los intents YAML **no** declaran campo `permission:`; la clave RBAC es el propio `intent_id`.

## Admin

### Catálogo único

Entrypoint: **`/admin/permission-catalog/index`**.

| Pantalla | URL |
|----------|-----|
| Catálogo (intents + capabilities) | `/permission-catalog/index` |
| Detalle intent | `/permission-catalog/view-intent?intent_id=…` |
| Detalle capability | `/permission-catalog/view-capability?capability_id=…` |
| Roles RBAC (CRUD) | `/user-management/role/index` |
| Integridad del catálogo | `/permission-catalog/integrity` |
| Editar roles de un intent | `/permission-catalog/edit-intent-roles?key=…` |
| Editar roles de una capability | `/permission-catalog/edit-capability-roles?key=…` |

Menú admin «Acceso a datos»: **Catálogo** + **Integridad**.

La portada del catálogo lista **permisos legacy deprecados** con enlace a la capability de reemplazo y roles que aún los tienen.

### Redirects legacy

| URL antigua | Destino |
|-------------|---------|
| `/data-access-catalog/*` | Catálogo de permisos |
| `/permission-catalog/edit-attribute-roles` | Catálogo de permisos |
| `/user-management/permission/*`, `/auth-item-group/*` | Catálogo (`LegacyRbacRedirectController`) |

## Herramientas CLI

```bash
cd web
php yii catalog-permission/sync-capabilities --applyDefaultRoles=1 --propagatePanel=1
php yii catalog-permission/sync              # Intents → auth_item + rutas API
php yii catalog-permission/migrate-grants      # Legacy / fuentes YAML → intents + capabilities
php yii catalog-permission/list-capabilities   # Inventario capabilities declaradas
php yii catalog-permission/prune-attributes    # Dry-run: Entidad.atributo.* a borrar
php yii catalog-permission/prune-attributes --execute=1
php yii catalog-integrity/check              # 0 errores esperado en CI
```

Orden recomendado en staging:

```bash
php yii migrate
php yii catalog-permission/sync-capabilities --applyDefaultRoles=1 --propagatePanel=1
php yii catalog-permission/sync
php yii catalog-permission/migrate-grants
# validar asistente, tablero guardia, captura encounter
php yii catalog-integrity/check
# opcional tras backup:
php yii catalog-permission/prune-attributes --execute=1
```

**Re-login** obligatorio tras cambios RBAC en producción/staging.

## Políticas de dominio (post-RBAC)

```
RBAC (¿tiene intent o capability?) → DomainOperationAuthorizer (¿sobre ESTE recurso?) → servicio de dominio
```

- **Metadata:** `schemas/domain-operation-policies.yaml`
- **Registry:** `DomainOperationPolicyRegistry` (`common/config/product-registries.php`)
- **API:** `ApiDomainOperationBridge`, `IntentRequestContextService` (header/body `intent_id` cuando aplica flow)

Ejemplo guardia: rol con `guardia.triage` puede llamar la API; el dominio sigue validando estado del episodio y efector.

## Capabilities vigentes (referencia)

| ID | Dominio |
|----|---------|
| `guardia.view_board`, `guardia.ingreso`, `guardia.triage`, `guardia.atender`, `guardia.retiro_administrativo`, `guardia.egreso_clinico`, `guardia.operaciones_staff` | Urgencias EMER |
| `encounter.ver_como_staff`, `encounter.capturar`, `encounter.documentar_nota` | Encounter clínico staff |
| `panel.staff_clinical`, `panel.paciente_home` | Panel home API |

Detalle de rutas: YAML en `permission/capabilities/` y vista admin por capability.

## Despliegue y validación

Checklist staging:

- [ ] Login `/auth/login` (staff y paciente)
- [ ] Tablero guardia: ingreso/triage/atender según rol (Administrativo, enfermería, Médico)
- [ ] Captura encounter y lectura HC staff
- [ ] `catalog-integrity/check` sin errores; revisar warnings legacy/capability
- [ ] Admin: catálogo intents + capabilities, asignación roles, sync capabilities
- [ ] Re-login tras deploy RBAC

**Rollback:** restaurar backup `auth_item` / `auth_assignment` antes de `prune-attributes --execute=1` en producción.

## Archivos de referencia

```
web/common/components/Platform/Core/Permission/
  BioenlaceAccessChecker.php, IntentAccessService.php, CapabilityAccessService.php
  BioenlaceRbacRevision.php, CapabilityManifestIndex.php, LegacyPermissionAliasIndex.php
  CapabilityPermissionSyncService.php, CatalogPermissionSyncService.php
  IntentGrantMigrationService.php, PermissionCatalogService.php

web/common/metadata/bioenlace/
  assistant/intents/
  permission/capabilities/
  permission/intent-grant-migration-map.yaml
  permission/legacy-permission-aliases.yaml
  ui/home_panel_manifest.yaml
```

## ADR relacionados

- [autorizacion-solo-por-intents.md](../decisions/autorizacion-solo-por-intents.md) — intents como permiso assignable base
- [autorizacion-capabilities-ui-nativa.md](../decisions/autorizacion-capabilities-ui-nativa.md) — capabilities para UIs nativas
- [asistente-lectura-data-access.md](./asistente-lectura-data-access.md) — lecturas: motor DataAccess + RBAC por intent
