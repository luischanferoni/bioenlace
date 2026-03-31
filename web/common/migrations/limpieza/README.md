## Limpieza RBAC / Webvimark

Este directorio contiene scripts manuales de mantenimiento (no son migraciones Yii).

- **Archivo**: `limpieza_webvimark_rbac_mariadb.sql`
- **Motor**: MariaDB
- **Qué hace**:
  - Elimina asignaciones a usuarios inexistentes.
  - Elimina rutas (`auth_item.type=3`) que no están asociadas a ningún permiso (`auth_item.type=2`).
  - Elimina permisos (`auth_item.type=2`) que no están asociados a ningún usuario y están desconectados del grafo RBAC.
  - Limpia relaciones rotas en `auth_item_child`.

### Cómo usar

1. Hacer backup de la base.
2. Abrir el SQL y ejecutar por secciones.
3. Cada sección trae un bloque **PREVIEW** (`SELECT`) para revisar impacto antes del `DELETE`.

