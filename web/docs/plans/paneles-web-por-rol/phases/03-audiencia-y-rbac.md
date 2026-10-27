# Fase 03 — Audiencia staff ampliada y RBAC de gestión

## Objetivo

Que los roles de gestión lleguen efectivamente al panel (audiencia correcta) y que la API no les responda 403.

## Tareas

- Agregar a `audience.staff_roles` los roles que la fase 00 marque como staff: como mínimo `AdminMinisterio`; evaluar `_x_efector_aditoria`, `_sin_efector_CEAMM`.
- Actualizar `default_roles` de la capability `panel.staff_clinical` en `common/components/Platform/Core/Permission/metadata/capabilities/panel.yaml` y crear la migración de sync a `auth_item` / `auth_item_child` para instalaciones existentes.
- Revisar `HomePanelAudienceResolver::resolve()`: hoy un usuario sólo con `AdminMinisterio` y sin efector/servicio cae en `FALLBACK`. Ajustar el orden de chequeos para que rol de gestión sin sesión operativa resuelva `STAFF`.
- Verificar que ningún rol nuevo quede con panel vacío: si el panel resuelve 0 secciones con encounter, hoy se lanza excepción; definir el comportamiento para sesión sin encounter seleccionado y para paneles de gestión.

## Criterio de salida

- `GET /api/v1/home/panel` responde 200 con panel no vacío para cada rol de la matriz aprobada.
- Migración aplicada y reversible.
- `HomePanelAudienceResolver` con tests de cada rama.

## Estado

Implementado (alcance mínimo).

- `audience.staff_roles` incluye `AdminMinisterio`: un usuario sólo con ese rol ya resuelve audiencia `staff` en lugar de `fallback`.
- `default_roles` de `panel.staff_clinical` incluye `AdminMinisterio`.
- Migración `m261027_120000_panel_staff_clinical_admin_ministerio`: sync incremental de capabilities (`applyDefaultRoles`), sin tocar grants existentes.

Pendiente: decidir si `_x_efector_aditoria`, `_sin_efector_CEAMM` y `facturista` necesitan panel propio; hoy quedan en `staff_operations` (o `fallback` si no tienen sesión operativa).
