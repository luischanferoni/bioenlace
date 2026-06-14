# Fase 3 — Login y usuarios (retiro webvimark)

## Objetivo

Desacoplar identidad y sesión del paquete `webvimark/module-user-management`, manteniendo tablas `user` / `auth_*` y `BioenlaceDbManager`.

## Hecho en esta fase (parcial)

| Pieza | Estado |
|-------|--------|
| `User::hasRole()` | Override en `common/models/User.php` → sesión Bioenlace + `authManager` |
| Admin RBAC legacy | `LegacyRbacRedirectController` → `/permission-catalog/index` (permission, role, auth-item-group) |
| Login backend admin | `UserConfig::afterLogin` usa `User::hasRole` + `BioenlaceAccessChecker::refreshForIdentity` |
| Login web canónico | `AuthController` + `LoginForm` en `/auth/login` (frontend y backend) |
| Vistas login | `frontend/views/login/login.php` y `loginLayout.php` sin webvimark |
| Modelo identidad | `common/models/User` AR propio (`IdentityInterface`); `identityClass` → `common\models\User` |
| Contraseña | `ChangeOwnPasswordForm`, `PasswordRecoveryForm` + `/auth/change-own-password`, `/auth/password-recovery*` |
| Confirmación e-mail | `ConfirmEmailForm` + `/auth/confirm-email`, `/auth/confirm-email-receive` |
| CRUD usuarios admin | `UserAccountController` + `UserRoleController` (controllerMap user-management) |
| UI RBAC helpers | `BioenlaceGhostHtml`, `BioenlaceGhostNav`, `RbacFreeRouteChecker`, `UserVisitLog` propios |
| Roles RBAC | `RbacRoleQueryService`, `common\models\rbac\AuthRole` (sin `webvimark\Role` en flujos activos) |

| `UserManagementCompatModule` | Módulo Yii stub en configs (backend, frontend, console) |
| `UserManagementCompat` | `t()` y menú admin sin webvimark |
| Checklist staging | `staging-validacion.md` |

## Pendiente

| Pieza | Notas |
|-------|-------|
| Composer | **Retirado** `webvimark/module-user-management` |
| Widgets grid | `GridPageSize`, `GridBulkActions`, `StatusColumn` en `common/components/Ui/Grid/` |
| `pathMap` vistas vendor | Eliminado en `common/config` y `backend/config` |

| Vistas legacy RBAC | Eliminadas (`role/`, `permission/`, `auth-item-group/`); redirect en `LegacyRbacRedirectController` |
| `SisseRole` | Eliminado (usar `RbacRoleQueryService` + `AuthRole`) |
| `user/update` backend | Vista añadida (faltaba para admin) |

## Qué queda con nombre histórico

- Comentarios en migraciones antiguas (`m2605*_webvimark_*`) — solo documentación de datos.
- Alias de clase `ApiGhostAccessControl` / `SisseGhostAccessControl` (delegan a Bioenlace).

## Verificación

- Vistas legacy (`nomenclador/*`, `internacion/*`, `SesionOperativaService`) responden igual con `User::hasRole`.
- URLs `/user-management/permission/index`, `/user-management/role/*`, `/user-management/auth-item-group/*` redirigen al catálogo.
- URLs `/auth/confirm-email` y legacy `user-management/auth/confirm-email*` redirigen al flujo Bioenlace.

## Despliegue (fases 1–2)

```bash
cd web
php yii migrate
php yii catalog-permission/sync
```

Re-login de usuarios staff para poblar `__bioenlace_user_*` en sesión.
