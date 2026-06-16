# RBAC y catálogo de permisos

Documentación estable del modelo de autorización Bioenlace: motor Yii, **permisos por `intent_id`**, catálogo unificado en admin, identidad sin webvimark.

## Principios

- **Motor RBAC:** `BioenlaceDbManager` (Yii) + caché en sesión (`BioenlaceSessionPermissions`) y chequeos (`BioenlaceAccessChecker`).
- **PES → rol:** se conserva la resolución vía `servicios.item_name`.
- **Permisos assignables** en admin: **solo intents** — clave = `intent_id` del YAML.
- **Atributos `Entidad.atributo.*`:** legacy; no se asignan en admin; eliminar de `auth_item` tras migración (ver ADR [autorizacion-solo-por-intents.md](../decisions/autorizacion-solo-por-intents.md)).
- **Web staff (SPA):** `frontend/controllers` exigen autenticación; el RBAC real vive en **API v1** (`BioenlaceApiAccessControl`).
- **Admin:** RBAC por ruta (`BioenlaceAdminAccessControl`).

## Capas

| Capa | Componentes | Responsabilidad |
|------|-------------|-----------------|
| API v1 | `BioenlaceApiAccessControl`, `ApiRoutePermissionResolver` | `403` si falta permiso de ruta / intent |
| Web SPA | `FrontendAuthenticatedAccessControl`, `EnforceGhostAccessBootstrap` | Solo login; sin enumerar intents en controllers |
| Sesión | `BioenlaceSessionPermissions`, `BioenlaceAccessChecker::refreshForIdentity` | Pobla `__bioenlace_user_*` tras login |
| Permiso intent | `IntentPermissionResolver::resolve()` | Devuelve `intent_id` como clave en `auth_item` |
| Dominio recurso | `DomainOperationAuthorizer`, políticas en `domain-operation-policies.yaml` | ¿Sobre **este** PES/turno/efector? |
| Admin catálogo | `PermissionCatalogController` | Intents, integridad, roles por intent |
| Identidad | `common\models\User`, `AuthController` | Login, contraseña, confirmación e-mail |

### Jerarquía RBAC (ejemplo)

```
rol → condicion-laboral.editar-propio (type 2) → /api/profesional-efector-servicio/editar-condicion-laboral (type 3)
```

## Fuentes de verdad

| Canal | Cadena |
|-------|--------|
| Operaciones de producto | `schemas/intents/{create,read,update,delete}/` + `intent-families.yaml` |
| Staff métricas / edición (migrado) | Intent con `metric_id` o `edit_surface_id` + executor DataAccess detrás de `open_ui` |
| Pasos UI dentro de flow | Derivados del intent; `FlowStepAccessService` + header `X-Flow-Intent-Id` |
| Campos editables | `fields` / `field_groups` en YAML del intent; whitelist en servicio de dominio (`IntentSubmitFieldFilter`) |

Los intents YAML **no** declaran campo `permission:`; la clave RBAC es el propio `intent_id`.

## Admin

### Catálogo único

Entrypoint: **`/admin/permission-catalog/index`**.

| Pantalla | URL |
|----------|-----|
| Catálogo de permisos (intents) | `/permission-catalog/index` |
| Detalle intent (campos, rutas) | `/permission-catalog/view-intent?intent_id=…` |
| Roles RBAC (CRUD + intents) | `/user-management/role/index` |
| Integridad del catálogo | `/permission-catalog/integrity` |
| Editar roles de un intent | `/permission-catalog/edit-intent-roles?key=…` |

Menú admin «Acceso a datos»: **Catálogo** + **Integridad**.

### Redirects legacy

| URL antigua | Destino |
|-------------|---------|
| `/data-access-catalog/*` | Catálogo de permisos |
| `/permission-catalog/edit-attribute-roles` | Catálogo de permisos |
| `/user-management/permission/*`, `/auth-item-group/*` | Catálogo (`LegacyRbacRedirectController`) |

## Herramientas CLI

```bash
cd web
php yii catalog-permission/sync              # Registra intents en auth_item y enlaza rutas API
php yii catalog-permission/migrate-grants    # Copia grants rol desde permisos legacy → intents
php yii catalog-permission/prune-attributes  # Dry-run: lista Entidad.atributo.* a borrar
php yii catalog-permission/prune-attributes --execute=1   # Borra tras backup y migrate-grants
php yii catalog-integrity/check              # 0 errores esperado en CI
```

Orden recomendado en staging: `migrate` → `sync` → `migrate-grants` → validar asistente/API → `prune-attributes` (dry-run) → `prune-attributes --execute=1` → `catalog-integrity/check`.

## Políticas de dominio (post-RBAC)

```
RBAC (¿tiene el intent?) → DomainOperationAuthorizer (¿sobre ESTE recurso?) → servicio de dominio
```

- **Metadata:** `schemas/domain-operation-policies.yaml`
- **Registry:** `DomainOperationPolicyRegistry` (`common/config/product-registries.php`)
- **API:** `ApiDomainOperationBridge`, `IntentRequestContextService` (header/body `intent_id`)

## Despliegue y validación

Checklist staging:

- [ ] Login `/auth/login` (staff y paciente)
- [ ] Grants intent piloto (condición laboral, profesionales, agenda)
- [ ] Asistente: familia `condicion-laboral.edit` resuelve propio vs staff
- [ ] `catalog-integrity/check` sin errores (incl. sin grants atributo en `auth_item`)
- [ ] Admin: catálogo, integridad, asignación roles por intent

**Rollback:** restaurar backup `auth_item` / `auth_assignment` antes de `prune-attributes --execute=1` en producción.

## Archivos de referencia

```
web/common/components/Platform/Core/Permission/
  BioenlaceAccessChecker.php, IntentManifestIndex.php, IntentMetricIndex.php
  IntentRequestContextService.php, IntentSubmitFieldFilter.php
  CatalogPermissionSyncService.php, IntentGrantMigrationService.php

web/common/metadata/bioenlace/
  assistant/intents/, permission/intent-grant-migration-map.yaml
```
