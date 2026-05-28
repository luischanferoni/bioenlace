# `common/` — organización del monorepo PHP

Código compartido por API v1, consola, jobs y (legacy) frontend Yii.

## Dominios (`components/` y `models/`)

| Dominio | `components/` | `models/` |
|---------|---------------|-----------|
| **Clinical** | `Clinical/` (Service, Workflow, `Emergency/`, `Inpatient/`, Prescription, Legacy, …) | `Clinical/` |
| **Scheduling** | `Scheduling/Service/` (turnos, `Service/Quirofano/`) | `Scheduling/Turno.php` (+ alias `models/Turno.php`) |
| **Person** | `Person/Service/` | `Person/Persona.php` (+ alias `models/Persona.php`) |
| **Organization** | `Organization/Service/` (PES, sesión operativa, efectores) | `ProfesionalEfectorServicio`, … |
| **Core** | `Core/Service/` (push, notificaciones, acciones comunes) | — |
| **Ui** | `Ui/` (`UiScreenService`, `UiDefinitionTemplateManager`) | — |
| **Assistant** | `Assistant/` (+ `Assistant/Service/` hints) | — |
| **Terminology** | `Terminology/` | `Terminology/Snomed/*` (+ alias `models/snomed/*`) |
| **Ai**, **Integrations**, **Infra**, **Text**, **Logging** | transversal | — |

## Reglas

1. **Leer** [docs/arquitectura/common-components.md](docs/arquitectura/common-components.md) antes de tocar `components/`.
2. **No** crear código bajo `components/Services/` ni carpetas top-level clínicas sueltas (`Emergency/`, `Inpatient/` → van bajo `Clinical/`).
3. **No** lógica de negocio en controllers API; usar `*/Service/` del dominio.
4. Código nuevo de modelos: preferir `models/{Dominio}/` y alias `@deprecated` en raíz solo si hace falta compatibilidad.
5. Legacy consulta (IA): `components/Clinical/Legacy/ConsultaProcesamientoService.php`.
6. API clínica: `/api/v1/clinical/...`.

## Migración Clinical

- [docs/plans/README.md](docs/plans/README.md) (solo planes en ejecución)
- [docs/decisions/fhir-clinical.md](docs/decisions/fhir-clinical.md)

## Herramientas

- `tools/inventory_ar_relations.php` — inventario de relaciones AR (opcional, desde `web/`).
