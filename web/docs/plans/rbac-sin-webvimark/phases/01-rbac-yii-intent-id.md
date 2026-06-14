# Fase 1 — RBAC Yii + intent_id

## Hecho

| Pieza | Ubicación |
|-------|-----------|
| Caché sesión permisos/rutas | `BioenlaceSessionPermissions` |
| Chequeos API / permiso | `BioenlaceAccessChecker`, `ApiRoutePermissionResolver` |
| Web SPA auth-only | `FrontendAuthenticatedAccessControl` + `EnforceGhostAccessBootstrap` |
| API v1 | `BioenlaceApiAccessControl` en `BaseController` |
| Clave permiso intent | `IntentPermissionResolver::resolve()` → `intent_id` |
| YAML | Eliminado campo `permission:` (38 archivos) |
| Migración grants | `m260630_100000_intent_id_permission_keys` |
| Backend catálogo | `BioenlaceBackendAccessControl` |
| `User::canRoute` | Override en `common/models/User.php` → Bioenlace |
| Nav / links condicionales | `NavSisse`, `SisseGhostHtml` vía `User::canRoute` |
| Controllers backend | 18 controllers → `BioenlaceBackendAccessControl` |
| Legacy aliases | `ApiGhostAccessControl`, `SisseGhostAccessControl` → Bioenlace |
| `User::hasRole` | Override → sesión Bioenlace + `authManager` |
| Admin RBAC webvimark | `LegacyRbacRedirectController` (permission, role, auth-item-group) |

## Pendiente

- Retirar paquete Composer `webvimark/module-user-management` (login, módulo user-management) — [fase 3](../03-login-usuarios-webvimark.md).
- Migrar CRUD usuarios y `user-permission/set` fuera de webvimark.
- Eliminar admin `user-management/*` del menú backend (usuarios siguen en webvimark hasta migrar login).

## Verificación

- Login staff → `site/index` sin 403 por ruta web.
- `GET /api/v1/home/panel` respeta rol.
- Rol paciente con grant `atencion.mis-atenciones-como-paciente` accede al listado de atenciones.
