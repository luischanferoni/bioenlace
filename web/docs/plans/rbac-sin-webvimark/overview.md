# Overview — RBAC sin webvimark

## Problema

Webvimark acoplaba login, caché de rutas (`AuthHelper`) y admin legacy de grupos/rutas. El catálogo declarativo (intents YAML + `data-access-config`) ya vive en metadata; los grants deben ser por **intent_id** y atributos atómicos, no por permisos lógicos `Entidad.operacion` duplicados.

## Objetivo

1. **Motor RBAC:** `BioenlaceDbManager` (Yii) + `BioenlaceSessionPermissions` / `BioenlaceAccessChecker`.
2. **Clave de intent:** `intent_id` en `auth_item` (type 2), enlazado a `rbac_route` API.
3. **Web staff:** `frontend/controllers` solo exigen autenticación; RBAC en API v1 (`BioenlaceApiAccessControl`).
4. **Admin:** una sola pantalla **Catálogo de permisos** (intents + atributos + roles por fila + edición por rol). Sin DataAccess catalog, sin campos BD, sin matriz «Roles ↔ permisos» separada.
5. **Grupos YAML:** solo en `data-access-config`; no administrables en panel.

## Fuera de alcance inmediato

- Quitar paquete Composer `webvimark/module-user-management` (login, CRUD usuarios) — ver [fase 3](phases/03-login-usuarios-webvimark.md).
- Reemplazar todas las vistas que usan `GhostHtml` (ya no `User::hasRole`: override en `common/models/User.php`).

## Criterios de cierre

- [ ] Migración `m260630_*` aplicada en entornos.
- [x] Admin: menú «Acceso a datos» = catálogo + integridad únicamente.
- [x] URLs legacy (`data-access-catalog`, `data-access-attribute-field`, `permission-catalog/roles`, `user-management/permission|role|auth-item-group`) redirigen al catálogo.
- [ ] `php yii catalog-integrity/check` sin errores.
- [x] Documentación estable actualizada en `arquitectura/rbac-catalogo-permisos.md`.
