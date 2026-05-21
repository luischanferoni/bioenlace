# `common/` — organización del monorepo PHP

Código compartido por API v1, consola, jobs y (legacy) frontend Yii.

## Dominios (`components/` y `models/`)

| Dominio | `components/` | `models/` |
|---------|---------------|-----------|
| **Clinical** | `Clinical/` (Service, Workflow, Enum, Legacy) | `Clinical/` |
| **Scheduling** | `Scheduling/Service/` (turnos, quirofano) | `Scheduling/Turno.php` (+ alias `models/Turno.php`) |
| **Person** | `Person/Service/` | `Person/Persona.php` (+ alias `models/Persona.php`) |
| **Organization** | `Organization/Service/` (PES, sesión operativa, efectores) | `ProfesionalEfectorServicio`, … |
| **Core** | `Core/Service/` (push, notificaciones, acciones comunes) | — |
| **Ui** | `Ui/` (`UiScreenService`, `UiDefinitionTemplateManager`) | — |
| **Assistant** | `Assistant/` (+ `Assistant/Service/` hints) | — |
| **Terminology** | `Terminology/` | `Terminology/Snomed/*` (+ alias `models/snomed/*`) |
| **Ai**, **Integrations**, **Infrastructure**, **Text**, **Logging** | transversal | — |

## Reglas

1. **No** crear código nuevo bajo `components/Services/` (eliminado — Fase 3).
2. **No** lógica de negocio en controllers API; usar `*/Service/` del dominio.
3. Código nuevo de modelos: preferir `models/{Dominio}/` y alias `@deprecated` en raíz solo si hace falta compatibilidad.
4. Legacy consulta (IA): `components/Clinical/Legacy/ConsultaProcesamientoService.php`.
5. API clínica: `/api/v1/clinical/...`.

## Migración Clinical

- [docs/plans/MIGRATION_STATUS.md](docs/plans/MIGRATION_STATUS.md)
- [docs/plans/PROGRAM.md](docs/plans/PROGRAM.md)

## Herramientas

- `tools/migrate_phase3_services.php` — migración one-shot Fase 3 (histórico; `Services/` ya no existe).
