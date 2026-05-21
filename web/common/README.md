# `common/` — organización del monorepo PHP

Código compartido por API v1, consola, jobs y (legacy) frontend Yii.

## Dominios

| Carpeta | Contenido |
|---------|-----------|
| `models/{Dominio}/` | ActiveRecord (solo persistencia) |
| `components/{Dominio}/` | Dto, Enum, Service, Repository, Workflow |
| `migrations/` | Migraciones Yii de BD |
| `config/` | Configuración compartida |

### Dominios previstos

- **`Clinical/`** — FHIR clínico: Encounter, CarePlan, órdenes (Fase 2+). Ver [docs/plans/PROGRAM.md](../docs/plans/PROGRAM.md).
- **`Scheduling/`** — Turnos (Appointment), agenda, quirófano.
- **`Person/`** — `Persona` (Patient).
- **`Organization/`** — Efector, servicio, PES, sesión operativa.
- **`Terminology/`** — SNOMED y nomencladores (hoy parte en `models/snomed/`).
- **`Assistant/`** — Asistente conversacional (`components/Assistant/`).
- **`Ui/`** — UI JSON API (`UiScreenService`, templates).
- **`Ai/`**, **`Integrations/`**, **`Infrastructure/`** — transversal.

## Reglas

1. **No** nueva lógica de negocio en `models/` ni en controllers API.
2. **No** carpeta `components/Services/` para código nuevo (en desuso; ver fase 3 del programa Clinical).
3. **No** DTOs en `models/`; usar `components/{Dominio}/Dto/`.
4. Nomenclatura FHIR: clases PHP = recurso (`CarePlan`, `MedicationRequest`); tablas = `snake_case` (`care_plan`, `medication_request`).
5. API clínica bajo **`/api/v1/clinical/...`** (sin alias `/consulta/`).

## Migración Clinical

Estado: [docs/plans/MIGRATION_STATUS.md](../docs/plans/MIGRATION_STATUS.md).
