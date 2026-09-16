# Clinical (`common/components/Domain/Clinical`)

Bounded context FHIR clínico. **Eje = módulo de capacidad** (sin Shared / Enum / Service / Infrastructure catch-all ni PHP suelto en la raíz del BC).

ADR: [`clinical-modulos-capacidad`](../../../docs/decisions/clinical-modulos-capacidad.md), [`shared-top-level-infrastructure`](../../../docs/decisions/shared-top-level-infrastructure.md), [`domain-folder-grammar`](../../../docs/decisions/domain-folder-grammar.md).

## Módulos

| Módulo | Contenido |
|--------|-----------|
| `Encounter/` | Núcleo (Domain, Service, Presentation, Legacy, flows `atencion.*`) |
| `Emergency/` | Guardia + flows `urgencias.*` |
| `Inpatient/` | Internación + agents + flows `internacion.*` |
| `Laboratory/` | Lab + External LIS + flows `laboratorio.*` |
| `Prescription/` | Receta + External + flows `receta.*` |
| `CarePlan/` | Planes, órdenes, protocolos, flows `tratamiento.*` |
| `CareCohort/` | Care packs + agents + Infrastructure batch |
| `PedidoAtencion/` | Catálogo línea×acto (`Domain/*Catalog`) |
| `Capture/` | Captura: `Domain/`, `Service/`, Text/SpeechToText adapters |
| `HistoryExchange/` | Cola HC + External |
| `LegalRecord/` | Export registro legal (`Service/`) |
| `Specialty/` | Odontología / oftalmología / inpatient aux (`<área>/Service|Domain`) |
| `Assistant/` `Home/` | Plugins Platform (solo en raíz BC) |

```text
<Modulo>/Application/{Flows,Agent}/
<Modulo>/Domain/
<Modulo>/Service/
<Modulo>/Presentation/              # opcional
<Modulo>/Infrastructure/External/   # ACL
```

Infra técnica compartida: `components/Shared/`. Scheduling / Terminology / Person son **otros BCs**.

Modelos AR: `common/models/Clinical/`.
