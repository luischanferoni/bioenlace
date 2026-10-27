# Fase 05 — Top menu por metadata

## Objetivo

Que AdminEfector y los roles de red no vean dropdowns de Servicio ni Encounter Type, sin condicionales de rol en la vista ni en el page builder.

## Patrón existente a extender

`ClientContextMetadata::shouldHideServicioInNavbar()` ya lee `web_staff.hide_servicio_navbar_roles` de `common/components/Platform/Ui/Presentation/client-context.yaml` (hoy sólo `Administrativo`), y `MainLayoutPageBuilder` expone `hideServicioNavbar` a `frontend/views/layouts/main.php`.

## Tareas

- Agregar en `client-context.yaml`, sección `web_staff`:
  - `hide_servicio_navbar_roles`: sumar `AdminEfector`, `AdminMinisterio`, `nivelCentral`.
  - `hide_encounter_navbar_roles`: nueva clave con los mismos roles.
- Nuevo método `ClientContextMetadata::shouldHideEncounterInNavbar(int $userId)` siguiendo el mismo patrón (roles vía `RbacRoleQueryService`, lista desde metadata).
- `MainLayoutPageBuilder::build()` pasa `hideEncounterNavbar` y deja de calcular nada por rol.
- Revisar `main.php`: los dropdowns sólo se renderizan con más de una opción; para AdminEfector con un solo efector ya se muestra como texto. Relevar el comportamiento actual y decidir si hay que ajustar copy.
- Evaluar renombrar la etiqueta del ítem de menú lateral («Pacientes») por rol vía el mismo `page builder` (`homeNavLabel`), sin lógica en la vista.

## Criterio de salida

- Un usuario AdminEfector ve sólo el efector; AdminMinisterio, sólo el efector.
- Ningún `if` nuevo por nombre de rol en `MainLayoutPageBuilder` ni en `main.php`.
- Tests de `ClientContextMetadata` para las dos claves.

## Estado

Pendiente.
