# Fase 05 — Top menu: se mantiene siempre visible (fase descartada)

## Decisión

El top menu (Efector → Servicio → Encounter Type) **se muestra siempre, para todos los roles**. No se ocultan dropdowns por rol. Producto lo confirmó: en ningún momento se pidió ocultarlos.

## Qué se había implementado y se revirtió

Se había extendido el patrón de `client-context.yaml` para ocultar Servicio y Encounter Type a `AdminEfector`, `AdminMinisterio` y `nivelCentral`. Revertido por completo, y además se quitó la regla previa que ocultaba el selector de servicio a `Administrativo`:

- `client-context.yaml`: `hide_servicio_navbar_roles: []` (ya no oculta a ningún rol) y se elimina `hide_encounter_navbar_roles`.
- `ClientContextMetadata`: se quitan `shouldHideEncounterInNavbar()` / `hideEncounterNavbarRoles()`; `shouldHideServicioInNavbar()` vuelve a su implementación original (queda sin efecto práctico porque la lista está vacía).
- `MainLayoutPageBuilder`: se quita `hideEncounterNavbar`.
- `frontend/views/layouts/main.php`: los dropdowns de encounter vuelven a renderizarse sin condición de rol.
- `ClientContextMetadataTest`: el caso de roles ocultos ahora verifica lista vacía.

Resultado: el top menu se ve completo para todos los roles (médico, enfermería, administrativo, admin de efector, admin de red).

Origen del malentendido: el `design.md` inicial del plan decía «AdminEfector y AdminMinisterio ocultan dropdowns de Servicio y Encounter Type», y de ahí salió la implementación. Esa línea también quedó corregida.

## Pendiente real

- Relevar qué ve hoy un AdminEfector con un solo efector: el dropdown de efector ya se muestra como texto plano cuando hay una sola opción (`main.php`), y eso es comportamiento previo.
- Etiqueta del menú lateral por rol («Pacientes» para todos hoy): sin definir.
