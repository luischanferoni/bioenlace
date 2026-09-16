# Clinical (`common/components/Domain/Clinical`)

Bounded context FHIR clínico. **Eje = módulo de capacidad** (sin Shared / Enum / Service / Infrastructure catch-all en la raíz del BC).

ADR: [`clinical-modulos-capacidad`](../../../docs/decisions/clinical-modulos-capacidad.md), [`shared-top-level-infrastructure`](../../../docs/decisions/shared-top-level-infrastructure.md).

## Módulos

| Módulo | Contenido |
|--------|-----------|
| `Encounter/` | Núcleo (Domain enums/catalogs, lifecycle, conditions, episodes, journey, summary, presenters, flows `atencion.*`) |
| `Emergency/` | Guardia + flows `urgencias.*` |
| `Inpatient/` | Internación + agents + flows `internacion.*` |
| `Laboratory/` | Lab + agents + Dtos + flows `laboratorio.*` + `Infrastructure/External` (LIS) |
| `Prescription/` | Receta + RDI agent + Dtos + flows `receta.*` + `Infrastructure/External` |
| `CarePlan/` | Planes, órdenes, protocolos, Dtos, flows `tratamiento.*` |
| `CareCohort/` | Care packs + followup agent + flows `care-packs.*` |
| `PedidoAtencion/` | Catálogo + línea×acto |
| `Capture/` | Documentación/captura (Workflow, Text, SpeechToText, issues) |
| `HistoryExchange/` | Cola HC + `Infrastructure/External` (HC nacional) |
| `LegalRecord/` | Export registro legal |
| `Specialty/` | Odontología / oftalmología |
| `Assistant/` `Home/` `DataAccess/` | Plugins Platform |

```text
<Modulo>/Application/{Flows,Agent}/
<Modulo>/Domain/
<Modulo>/Service/
<Modulo>/Infrastructure/External/   # ACL del módulo
```

Infra técnica compartida: `components/Shared/`. Scheduling / Terminology / Person son **otros BCs**, no módulos de Clinical.

Modelos AR: `common/models/Clinical/`.
