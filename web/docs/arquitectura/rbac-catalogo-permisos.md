# RBAC y catálogo de permisos

Documentación estable del modelo de autorización Bioenlace: motor Yii, permisos por `intent_id`, catálogo unificado en admin, identidad sin webvimark.

## Principios

- **Motor RBAC:** `BioenlaceDbManager` (Yii) + caché en sesión (`BioenlaceSessionPermissions`) y chequeos (`BioenlaceAccessChecker`).
- **PES → rol:** se conserva la resolución vía `servicios.item_name`.
- **Permisos assignables** en admin:
  - **Intents** — clave = `intent_id` del YAML (no nombres lógicos `Entidad.operacion` duplicados).
  - **Atributos** — `Entidad.atributo.read|info|edit` declarados en `data-access-config`.
- **Grupos** en `data-access-config`: solo presentación en YAML; no se administran en el panel.
- **Web staff (SPA):** `frontend/controllers` exigen autenticación; el RBAC real vive en **API v1** (`BioenlaceApiAccessControl`).
- **Backend admin:** RBAC por ruta (`BioenlaceBackendAccessControl`).

## Capas

| Capa | Componentes | Responsabilidad |
|------|-------------|-----------------|
| API v1 | `BioenlaceApiAccessControl`, `ApiRoutePermissionResolver` | `403` si falta permiso de ruta / intent |
| Web SPA | `FrontendAuthenticatedAccessControl`, `EnforceGhostAccessBootstrap` | Solo login; sin enumerar intents en controllers |
| Sesión | `BioenlaceSessionPermissions`, `BioenlaceAccessChecker::refreshForIdentity` | Pobla `__bioenlace_user_*` tras login |
| Permiso intent | `IntentPermissionResolver::resolve()` | Devuelve `intent_id` como clave en `auth_item` |
| Admin catálogo | `PermissionCatalogController` | Intents + atributos + roles por fila + `edit-role` |
| Identidad | `common\models\User`, `AuthController`, formularios en `common/models/forms/` | Login, contraseña, confirmación e-mail |
| Usuarios admin | `UserAccountController`, `UserRoleController` | CRUD `user` y asignación de roles |
| Rutas libres | `RbacFreeRouteChecker` | Login, error, permiso común guest |
| UI condicional | `BioenlaceGhostHtml`, `BioenlaceGhostNav`, `User::canRoute()` | Enlaces/menús según permiso de ruta |

### Jerarquía RBAC (ejemplo)

```
rol → atencion.mis-atenciones-como-paciente (type 2) → /api/clinical/encounter-patient-summary/listar-atenciones-como-paciente (type 3)
```

## Fuentes de verdad

| Canal | Cadena |
|-------|--------|
| Create / update / delete complejo | BD → AR → `schemas/intents/{create,update,delete}/` |
| Read / info por atributo | BD → AR → `data-access-config` |
| Edit escalar | BD → AR → `data-access-config` (`edit.attributes`) |
| Pasos UI dentro de flow | Derivados del intent (sin grant propio); `FlowStepAccessService` + header opcional `X-Flow-Intent-Id` |

Los intents YAML **no** declaran campo `permission:`; la clave RBAC es el propio `intent_id`.

## Admin

### Catálogo único

Entrypoint: **`/admin/permission-catalog/index`**.

| Pantalla | URL |
|----------|-----|
| Catálogo de permisos | `/permission-catalog/index` |
| Roles RBAC (CRUD + intents) | `/user-management/role/index` |
| Integridad del catálogo | `/permission-catalog/integrity` |
| Editar roles de un intent | `/permission-catalog/edit-intent-roles?key=…` |
| Editar roles de un atributo | `/permission-catalog/edit-attribute-roles?key=…` |

Menú backend «Acceso a datos»: solo **Catálogo** + **Integridad**.

### Redirects legacy

| URL antigua | Destino |
|-------------|---------|
| `/data-access-catalog/*` | Catálogo de permisos |
| `/data-access-attribute-field/*` | Catálogo de permisos |
| `/permission-catalog/roles` | Catálogo de permisos |
| `/user-management/permission/*`, `/auth-item-group/*` | Catálogo (`LegacyRbacRedirectController`) |
| `/user-management/role/*` (excepto CRUD activo) | `RbacRoleController` |
| `/user-management/auth/*` | `/auth/*` |

### Usuarios y roles

| Función | URL / controlador |
|---------|-------------------|
| Listado / CRUD usuarios | `/user-management/user/*` → `UserAccountController` |
| CRUD roles RBAC | `/user-management/role/*` → `RbacRoleController` |
| Asignar roles a usuario | `/user-management/user-permission/set` → `UserRoleController` |
| Asignar intents a roles (por permiso) | `/permission-catalog/edit-intent-roles?key=…` |
| Asignar intents a un rol (vista inversa) | `/user-management/role/update?name=…` |
| Asignar atributos a roles | `/permission-catalog/edit-attribute-roles?key=…` |
| Login web | `/auth/login`, `/auth/logout`, `/auth/change-own-password`, … |
| Alta desde persona (frontend) | `/user/crear` → `frontend\controllers\UserController` |

Módulo Yii: `UserManagementCompatModule` (configs backend, frontend, console). Traducciones/menú: `UserManagementCompat`.

## Identidad y sesión

- **Modelo:** `common\models\User` implementa `IdentityInterface`; tablas `user`, `auth_*` sin cambio de esquema.
- **Login:** `frontend/controllers/AuthController` (+ herencia en backend); vistas en `frontend/views/login/`.
- **Mail:** `yii\symfonymailer\Mailer` (`common/config/mailer.php`); sin `mailerDsn` en params-local → `useFileTransport` (runtime/mail).
- **API JWT:** `JsonHttpBearerAuth` valida token, persona y sesión; contexto operativo (efector, PES) se fija aparte (`SesionOperativaService`).
- **Paciente móvil:** puede no ejecutar `set-session`; endpoints que requieren efector deben pedirlo en body/query o responder `400`.
- Tras login staff: **re-login** necesario tras despliegues RBAC para refrescar `__bioenlace_user_*` en sesión.

## Herramientas CLI

```bash
cd web
php yii catalog-permission/sync      # Registra intents/atributos en auth_item y enlaza rutas API
php yii catalog-integrity/check      # Integridad catálogo (0 errores esperado)
php yii catalog-permission/seed-permissions   # Re-aplica permission inferido a intents nuevos (si aplica)
```

Migración clave de claves intent: `m260630_100000_intent_id_permission_keys`.

## Permisos atómicos de atributos

- **`AttributePermissionKeyMapper`:** grupo + operación DataAccess → `Entidad.atributo.read|info|edit`.
- **`AttributePermissionEvaluator`:** evalúa vía `auth_item` + scope desde YAML.

| DataAccess | Permiso catálogo |
|------------|------------------|
| `filter`, `read` | `.read` |
| `aggregate` | `.info` |
| `write` | `.edit` |

### Políticas de dominio (post-RBAC)

```
RBAC (¿puede intentar Entidad.operacion?) → DomainOperationAuthorizer (¿sobre ESTE recurso?) → servicio de dominio
```

- **Metadata:** `schemas/domain-operation-policies.yaml`
- **Registry:** `DomainOperationPolicyRegistry`
- **API:** `ApiDomainOperationBridge`, `EfectorAccessService`, `EncounterAccessService`

`scope_checker` en DataAccess sigue siendo ABAC del canal métricas/edición dispersa.

## Rutas ghost y herencia

- `inheritRoleGrantsFromRoute` sube la jerarquía `auth_item_child` (rol → permiso → ruta).
- `RbacRouteGhostInheritanceService` propaga **rol → ruta hija** desde roles con acceso a la ruta padre (internación, UI clínica).

## Alias históricos (delegan a Bioenlace)

- `ApiGhostAccessControl` → `BioenlaceApiAccessControl`
- `SisseGhostAccessControl` → `BioenlaceBackendAccessControl`

## Archivos de referencia

```
web/common/components/Core/Permission/
  BioenlaceAccessChecker.php
  BioenlaceSessionPermissions.php
  BioenlaceGhostHtml.php, BioenlaceGhostNav.php
  IntentPermissionResolver.php, ApiRoutePermissionResolver.php
  RbacFreeRouteChecker.php, RbacRoleQueryService.php

web/frontend/modules/api/v1/components/
  BioenlaceApiAccessControl.php
  JsonHttpBearerAuth.php

web/frontend/components/
  BioenlaceBackendAccessControl.php
  FrontendAuthenticatedAccessControl.php

web/common/models/User.php
web/common/models/forms/{LoginForm,ChangeOwnPasswordForm,...}.php
web/common/modules/UserManagementCompatModule.php
web/backend/controllers/{PermissionCatalogController,RbacRoleController,UserAccountController,UserRoleController,LegacyRbacRedirectController}.php
web/common/components/Ui/Grid/{GridPageSize,GridBulkActions,StatusColumn}.php
```

## Despliegue y validación

```bash
cd web
php yii migrate --interactive=0
php yii catalog-permission/sync
php yii catalog-integrity/check
```

Checklist staging:

- [ ] Login `/auth/login` (staff y paciente)
- [ ] `GET /api/v1/home/panel` respeta rol
- [ ] Grant `atencion.mis-atenciones-como-paciente` → listado propio; sin grant → `403` API
- [ ] Admin: catálogo, integridad, usuarios, asignación roles, `edit-role`
- [ ] URLs legacy RBAC → redirect catálogo
- [ ] Menús `BioenlaceGhostNav` / `User::canRoute` coherentes
- [ ] Impersonate `/user/impersonate` y alta `/user/crear`

**Rollback:** restaurar backup `auth_item` / `auth_assignment` o revertir `m260630_100000_intent_id_permission_keys` en entorno de prueba antes de producción.

## Migraciones relevantes (cronología)

- `m260621_*` … `m260629_*` — catálogo, cierre `data_access_role_grant`, rutas deprecadas
- `m260630_100000_intent_id_permission_keys` — claves permiso = `intent_id`
- Cierre legacy internación/agenda/notificaciones documentado en migraciones `m260626`–`m260629`
