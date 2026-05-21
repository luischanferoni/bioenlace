# Fase 3 — Reordenar `common/` (dominios, disolver `Services/`)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 2](./02-common-clinical.md) (puede solaparse parcialmente con 2 al final)  
**Estado:** pendiente

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

- [ ] Buscar/reemplazar namespaces en `frontend/modules/api/v1`, `console`, `Assistant`.
- [ ] Alias Yii `@common` sin cambios; solo namespaces PSR-4 si se ajusta `composer.json`.

## Documentación

- [ ] `common/README.md` con árbol de dominios y regla “no más Services/”.
- [ ] Actualizar `.cursor/rules` si mencionan `Services/Consulta`.

## Fuera de alcance

- Cambiar rutas HTTP.
- Yii web controllers.

## Definition of Done

- Carpeta `common/components/Services/` **vacía o eliminada**.
- `composer dump-autoload` / app arranca; smoke test API turnos (`listar-como-paciente`).
- Grep sin `common\components\Services\` salvo menciones en docs históricos.

## Siguiente fase

[Fase 4 — API clinical core](./04-api-clinical-core.md)
