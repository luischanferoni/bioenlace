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

## Pendiente

| Pieza | Notas |
|-------|-------|
| Confirmación de e-mail | Sustituir `user-management/auth/confirm-email` |
| CRUD usuarios | Migrar `user-management/user/*` y `user-permission/set` al admin Bioenlace o API |
| Composer | Quitar `webvimark/module-user-management` cuando no queden referencias |
| `pathMap` vistas vendor | Eliminar overrides en `backend/config/main.php` |

## Qué se mantiene temporalmente

- Confirmación de e-mail en `user-management/auth/confirm-email`.
- CRUD usuarios webvimark (`user-management/user`, `user-permission/set`) para alta de cuentas staff.
- `UserVisitLog` webvimark (registro de visitas post-login).

## Verificación

- Vistas legacy (`nomenclador/*`, `internacion/*`, `SesionOperativaService`) responden igual con `User::hasRole`.
- URLs `/user-management/permission/index`, `/user-management/role/*`, `/user-management/auth-item-group/*` redirigen al catálogo.
- URLs `/auth/login`, `/site/login` y legacy `/user-management/auth/login` muestran el formulario Bioenlace.

## Despliegue (fases 1–2)

```bash
cd web
php yii migrate
php yii catalog-permission/sync
```

Re-login de usuarios staff para poblar `__bioenlace_user_*` en sesión.
