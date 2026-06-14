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

## Pendiente

- Sustituir `SisseGhostAccessControl` en resto de controllers backend.
- Migrar `NavSisse` / `SisseGhostHtml` a `BioenlaceAccessChecker`.
- Retirar dependencia webvimark (login + `common/models/User`).

## Verificación

- Login staff → `site/index` sin 403 por ruta web.
- `GET /api/v1/home/panel` respeta rol.
- Rol paciente con grant `atencion.mis-atenciones-como-paciente` accede al listado de atenciones.
