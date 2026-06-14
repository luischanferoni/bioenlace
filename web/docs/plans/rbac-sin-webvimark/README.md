# Plan — RBAC Bioenlace (sin webvimark)

| Campo | Valor |
|-------|--------|
| Slug | `rbac-sin-webvimark` |
| Estado | En curso (fases 1–2 cerradas; fase 3 ~95%; retiro Composer pendiente) |
| Objetivo | RBAC sobre Yii (`BioenlaceDbManager`); permisos por `intent_id`; web SPA auth-only; admin unificado en catálogo de permisos |

## Índice

- [overview.md](./overview.md)
- [phases/01-rbac-yii-intent-id.md](./phases/01-rbac-yii-intent-id.md) — **cerrada** (retiro webvimark → fase 3)
- [phases/02-admin-catalogo-unico.md](./phases/02-admin-catalogo-unico.md) — **cerrada**
- [phases/03-login-usuarios-webvimark.md](./phases/03-login-usuarios-webvimark.md) — **casi cerrada** (stub módulo listo; falta Composer)
- [staging-validacion.md](./staging-validacion.md) — checklist despliegue

## Al cerrar

Volcar en `web/docs/arquitectura/rbac-catalogo-permisos.md` y borrar esta carpeta.
