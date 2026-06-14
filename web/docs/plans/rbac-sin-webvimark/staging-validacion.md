# Validación staging — RBAC sin webvimark

Checklist tras desplegar migración y sync. Ejecutar con usuario **staff** y usuario **paciente** reales (o de prueba con grants correctos).

## Despliegue

```bash
cd web
php yii migrate --interactive=0
php yii catalog-permission/sync
php yii catalog-integrity/check
```

Todos los usuarios staff deben **cerrar sesión y volver a entrar** (pobla `__bioenlace_user_*`).

## Login y sesión

- [ ] `GET /auth/login` muestra formulario Bioenlace
- [ ] Login staff → redirige a `site/index` o wizard sesión operativa (sin 403 por ruta web)
- [ ] Login admin backend → acceso a `/admin` con rol `_x_efector_AdminSisse`
- [ ] `GET /api/v1/home/panel` con JWT de sesión web respeta permisos

## Permisos por intent_id

- [ ] Rol con grant `atencion.mis-atenciones-como-paciente` → listado de atenciones propias vía API/asistente
- [ ] Rol sin grant → `403` en endpoint API correspondiente (no solo ocultar en UI)
- [ ] Superadmin accede a catálogo e integridad

## Admin

- [ ] Menú «Acceso a datos» → solo Catálogo + Integridad
- [ ] `/user-management/user/index` lista usuarios (UserAccountController)
- [ ] Asignar rol a usuario → `/user-management/user-permission/set`
- [ ] Editar permisos de rol → `/permission-catalog/edit-role?role=…`
- [ ] URLs legacy `/user-management/permission/index` → redirect catálogo

## Regresiones frecuentes

- [ ] `User::canRoute` en menús legacy (SisseGhostHtml / BioenlaceGhostNav)
- [ ] Impersonate desde listado usuarios (`/user/impersonate`)
- [ ] Alta usuario desde persona (`/user/crear`)

## Rollback

Si falla RBAC tras migración: restaurar backup BD `auth_item` / `auth_assignment` o revertir migración `m260630_100000_intent_id_permission_keys` en entorno de prueba antes de producción.
