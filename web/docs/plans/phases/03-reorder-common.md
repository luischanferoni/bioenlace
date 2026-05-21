# Fase 3 — Reordenar `common/` (dominios, disolver `Services/`)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 2](./02-common-clinical.md) (puede solaparse parcialmente con 2 al final)  
**Estado:** hecho (2026-05-21)

## Objetivo

Aplicar la estructura por dominios en todo `common/`: mover Turnos, Persona, Ui, Terminology; **eliminar** `common/components/Services/`.

## Movimientos planificados

| Origen | Destino |
|--------|---------|
| `components/Services/Turnos/*` | `components/Scheduling/Service/` |
| `components/Services/Persona/*` | `components/Person/Service/` |
| `components/Services/SesionOperativa/*` | `components/Organization/Service/` o `Person/` |
| `components/Services/Notificaciones/*` | `components/Core/Service/` |
| `components/Services/RegistroService.php` | `components/Person/Service/` |
| `components/Services/Quirofano/*` | `components/Scheduling/Service/Quirofano/` o dominio `Surgical/` |
| `components/Services/Assistant/*` | `components/Assistant/` (ya existe; consolidar) |
| `models/Turno.php` | `models/Scheduling/Turno.php` |
| `models/Persona.php` | `models/Person/Persona.php` |
| `models/snomed/*` | `models/Terminology/Snomed/` |
| `components/UiScreenService.php` | `components/Ui/UiScreenService.php` |
| `components/UiDefinitionTemplateManager.php` | `components/Ui/` |

## Actualización de imports

- [x] Namespaces actualizados en API, consola, Assistant, common.
- [x] `@common` sin cambios.

## Documentación

- [x] `common/README.md` con árbol de dominios.
- [x] `models/Turno` → `models/Scheduling/Turno.php` (+ alias raíz).
- [x] `models/Persona` → `models/Person/Persona.php` (+ alias raíz).
- [x] `models/snomed/*` → `models/Terminology/Snomed/*` (+ alias en `snomed/`).

## Fuera de alcance

- Cambiar rutas HTTP.
- Yii web controllers.

## Definition of Done

- [x] `common/components/Services/` **eliminada** (migrado a dominios; script histórico `tools/migrate_phase3_services.php`).
- [x] `UiScreenService` / `UiDefinitionTemplateManager` en `components/Ui/`.
- [ ] Smoke test API turnos en entorno con BD (manual).
- [x] Grep PHP sin `common\components\Services\` en código de aplicación.

## Siguiente fase

[Fase 4 — API clinical core](./04-api-clinical-core.md)
