# Fase 2 — Admin: catálogo único

## Objetivo

Un solo entrypoint: **`/admin/permission-catalog/index`**.

## Quitar del admin

| Pantalla | Acción |
|----------|--------|
| Catálogo DataAccess | Redirigir a catálogo de permisos (tab Atributos cubre claves) |
| Campos por atributo/grupo (BD) | Redirigir; gestión técnica fuera del menú (CLI/migraciones si hace falta) |
| Roles ↔ permisos (matriz) | Eliminar; columnas «Roles con acceso» + `edit-role` en index |

## Mantener

- **Integridad del catálogo** (`/permission-catalog/integrity`)
- **Editar rol** (`/permission-catalog/edit-role?role=…`) — asignación checkbox intents + atributos
- **Sincronizar → auth_item** (POST sync)

## Menú backend «Acceso a datos»

- Catálogo de permisos
- Integridad del catálogo

## Checklist

- [x] `backend/views/layouts/main.php` sin entradas legacy
- [x] `permission-catalog/index` sin enlaces a DataAccess ni roles
- [x] `actionRoles` → redirect index
- [x] `DataAccessCatalogController` / `DataAccessAttributeFieldController` → redirect index
- [x] Alertas «sin registrar en auth_item» / «sin roles» en index
