# `common/` — organización del monorepo PHP

Código compartido por API v1, consola, jobs y (legacy) frontend Yii.

## Dominios (`components/` y `models/`)

| Dominio | `components/` | `models/` |
|---------|---------------|-----------|
| **Clinical** | `Clinical/` (Service, Workflow, Enum) | `Clinical/` |
| **Scheduling** | `Scheduling/Service/` (turnos, quirofano) | `Turno` (raíz; → `Scheduling/` en fase posterior) |
| **Person** | `Person/Service/` | `Persona` (raíz) |
| **Organization** | `Organization/Service/` (PES, sesión operativa, efectores) | `ProfesionalEfectorServicio`, … |
| **Core** | `Core/Service/` (push, notificaciones, acciones comunes) | — |
| **Ui** | `Ui/` (`UiScreenService`, `UiDefinitionTemplateManager`) | — |
| **Assistant** | `Assistant/` (+ `Assistant/Service/` hints) | — |
| **Terminology** | `Terminology/` | `snomed/` (→ `Terminology/Snomed/` pendiente) |
| **Ai**, **Integrations**, **Infrastructure**, **Text**, **Logging** | transversal | — |

## Reglas

1. **No** crear código nuevo bajo `components/Services/` (eliminado en Fase 3).
2. **No** lógica de negocio en controllers API; usar `*/Service/` del dominio.
3. Legacy consulta (IA): `components/Clinical/Legacy/ConsultaProcesamientoService.php`.
4. API clínica futura: `/api/v1/clinical/...`.

## Migración Clinical

- [docs/plans/MIGRATION_STATUS.md](docs/plans/MIGRATION_STATUS.md)
- [docs/plans/PROGRAM.md](docs/plans/PROGRAM.md)

## Herramientas

- `tools/migrate_phase3_services.php` — migración one-shot Fase 3 (histórico).
