# Fase 02 — Resolución de dos ejes (rol × encounter class)

## Objetivo

Que el manifest pueda resolver un panel por `rol` y por `encounter_class` a la vez, con una cadena de fallback explícita, sin romper el contrato actual de un solo eje.

## Problema a resolver

Hoy `HomePanelManifest::resolveForStaff(?string $encounterClass)` resuelve **sólo** por encounter: si llega `null` va directo a `panels.staff_operations`. No hay eje de rol, y los overlays por rol no tienen dónde vivir. Como todo rol de personal de salud se cruza con encounter, la resolución debe combinar los dos ejes y no sólo agregar un caso para roles de gestión.

## Tareas

- **AdminEfector elige encounter**: quitar el caso especial que hoy omite el encounter para el servicio `AdminEfector` (`EstablishOperativeSession::ITEM_NAME_SERVICIO_ADMIN_EFECTOR` → `omiteEncounter`) y ajustar `isSesionOperativaCompleta()`, para que su sesión se comporte como el resto y pueda tener paneles por AMB/EMER/IMP/VR.
- Extender la firma a `resolve(string $audience, ?string $encounterClass, ?string $role)` y `resolveForStaff(?string $encounterClass, ?string $role)`; `HomePanelService` pasa el rol de la fase 01 (que ya sale del servicio elegido en la sesión).
- Cadena de resolución, en este orden:
  1. `panels.staff_by_role.<rol>.<encounter>`
  2. `panels.staff_by_role.<rol>.""` (sesión sin encounter, transición)
  3. `panels.staff.<encounter>` (con los slices actuales de IMP)
  4. `panels.staff_operations`
  5. `panels.fallback`
- Semántica de overlay explícita y testeable:
  - `sections`: **reemplaza** la lista del panel base.
  - `sections_append`: agrega al final del resultado base.
  - `exclude_sections`: quita ids del resultado base.
  - `title` / `layout`: override opcional.
- Resolver de variantes de rol registrado en `common/config/product-registries.php` bajo `homePanelStaffPanelSliceResolvers` (o clave nueva `homePanelRoleOverlayResolvers` si se prefiere separar), para permitir override por `params-local` y apagado sin tocar core. Reutilizar el patrón de `HomePanelStaffPanelSliceResolverInterface` y `InpatientHomePanelSliceResolver`.
- Aceptar clave `""` en YAML para «sesión sin encounter» y documentarlo en el header del manifest.

## Criterio de salida

- Tests en `HomePanelManifestTest`: matriz rol×encounter, cada escalón del fallback, overlay vacío, `sections_append`, `exclude_sections`, multicrol por precedencia.
- Ningún panel existente (AMB, EMER, IMP, VR, patient, fallback) cambia de salida.
- Decisión de merge y fallback escrita en [design.md](../design.md).

## Estado

Implementado.

- `HomePanelManifest::resolve($audience, $encounterClass, $role)` y `resolveForStaff($encounterClass, $role)`; `HomePanelService` pasa el rol.
- Cadena: `staff_by_role.<rol>.<encounter>` → `staff_by_role.<rol>.""` → `staff.<encounter>` (con slices) → `staff_operations` → `fallback`.
- Overlay: `sections` reemplaza, `sections_append` agrega, `exclude_sections` quita, `title`/`layout` override. Un overlay sin `sections` ni `sections_append` se rechaza en test.
- Configuración activa: enfermería y administrativo en AMB (cola del servicio), admin_ministerio sin encounter (agrega indicadores de red).
- Tests en `HomePanelManifestTest`: overlay por rol, panel sin overlay intacto, overlay que no aplica a otros encounters.

Pendiente: el caso AdminEfector-elige-encounter (también en esta fase) no se implementó todavía. Sin correr en PHP 8.3; validado con `php -l` y un harness sobre el YAML real (AMB/EMER/VR y staff_operations).
