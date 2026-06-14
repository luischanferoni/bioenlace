# `common/` — organización del monorepo PHP

Código compartido por API v1, consola, jobs y (legacy) frontend Yii.

## Dominios (`components/` y `models/`)

| Dominio | `components/` | `models/` |
|---------|---------------|-----------|
| **Clinical** | `Domain/Clinical/` (Service, Workflow, `Emergency/`, `Inpatient/`, Prescription, Legacy, `Text/`, …) | `Clinical/` |
| **Scheduling** | `Domain/Scheduling/Service/` (turnos, `Service/Quirofano/`) | `Scheduling/Turno.php` (+ alias `models/Turno.php`) |
| **Person** | `Domain/Person/Service/` | `Person/Persona.php` (+ alias `models/Persona.php`) |
| **Organization** | `Domain/Organization/Service/` (PES, sesión operativa, efectores) | `ProfesionalEfectorServicio`, … |
| **Plataforma** | `Platform/Core/`, `Platform/Ui/`, `Platform/Assistant/`, `Platform/Ai/`, `Platform/Infra/` | — |
| **Terminology** | `Domain/Terminology/` | `Terminology/Snomed/*` (modelos AR) |
| **Integrations** | `Domain/Integrations/` | — |

## Reglas

1. **Leer** [docs/arquitectura/common-components.md](docs/arquitectura/common-components.md) antes de tocar `components/`.
2. **No** crear código bajo `components/Services/` ni carpetas top-level clínicas sueltas (`Emergency/`, `Inpatient/` → van bajo `Clinical/`).
3. **No** lógica de negocio en controllers API; usar `*/Service/` del dominio.
4. Código nuevo de modelos: preferir `models/{Dominio}/` y alias `@deprecated` en raíz solo si hace falta compatibilidad.
5. Legacy consulta (IA): `components/Domain/Clinical/Legacy/ConsultaProcesamientoService.php`.
6. API clínica: `/api/v1/clinical/...`.

## Migración Clinical

- [docs/plans/README.md](docs/plans/README.md) (solo planes en ejecución)
- [docs/decisions/fhir-clinical.md](docs/decisions/fhir-clinical.md)

## Herramientas

- `tools/inventory_ar_relations.php` — inventario de relaciones AR (opcional, desde `web/`).
