# Clinical (`common/components/Domain/Clinical`)

Bounded context FHIR clínico. **Eje = módulo de capacidad** (sin Shared / Enum / Service catch-all).

Plan archivado. ADR: [`clinical-modulos-capacidad`](../../../docs/decisions/clinical-modulos-capacidad.md).

## Módulos

| Módulo | Contenido |
|--------|-----------|
| `Encounter/` | Núcleo (Domain enums/catalogs, lifecycle, conditions, episodes, journey, summary, presenters, flows `atencion.*`) |
| `Emergency/` | Guardia + flows `urgencias.*` |
| `Inpatient/` | Internación + agents + flows `internacion.*` |
| `Laboratory/` | Lab + agents + Dtos + flows `laboratorio.*` + External ACL |
| `Prescription/` | Receta + RDI agent + Dtos + flows `receta.*` |
| `CarePlan/` | Planes, órdenes, protocolos, Dtos, flows `tratamiento.*` |
| `CareCohort/` | Care packs + followup agent + flows `care-packs.*` |
| `PedidoAtencion/` | Catálogo + línea×acto |
| `Capture/` | Documentación/captura (Workflow, Text, SpeechToText, issues) |
| `HistoryExchange/` | Cola HC + IntegrationRetry |
| `LegalRecord/` | Export registro legal |
| `Specialty/` | Odontología / oftalmología |
| `Infrastructure/External/` | ACL LIS / receta / HC |
| `Assistant/` `Home/` `DataAccess/` | Plugins Platform |

```text
<Modulo>/Application/{Flows,Agent}/
<Modulo>/Domain/
<Modulo>/Service/
<Modulo>/Dto|Presentation|…   # según necesidad
```

Modelos AR: `common/models/Clinical/`.
