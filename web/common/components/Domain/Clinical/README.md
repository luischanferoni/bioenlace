# Clinical (`common/components/Domain/Clinical`)

Bounded context FHIR clínico. **Eje = módulo de capacidad** (sin Shared / Enum / Service catch-all ni PHP suelto en la raíz del BC).

ADR: [`clinical-modulos-capacidad`](../../../docs/decisions/clinical-modulos-capacidad.md), [`shared-top-level-infrastructure`](../../../docs/decisions/shared-top-level-infrastructure.md), [`domain-folder-grammar`](../../../docs/decisions/domain-folder-grammar.md), [`ddd-norte-modelo-rico`](../../../docs/decisions/ddd-norte-modelo-rico.md) (norte: Domain rico; piloto Capture).

## Módulos

| Módulo | Contenido |
|--------|-----------|
| `Encounter/` | Núcleo + aggregates + **Documentation/** (persistir nota / conditions / care plan) |
| `Emergency/` | Guardia + flows `urgencias.*` |
| `Inpatient/` | Internación + agents + flows `internacion.*` |
| `Laboratory/` | Lab + External LIS + flows `laboratorio.*` |
| `Prescription/` | Receta + External + flows `receta.*` |
| `CarePlan/` | Planes, órdenes, protocolos, flows `tratamiento.*` |
| `CareCohort/` | Care packs + agents + Infrastructure batch |
| `PedidoAtencion/` | Línea×acto: Domain (VO/ports/catálogos) + Application + External |
| `Capture/` | Intake clínico + aggregate `ClinicalCapture`; ver [Capture/README.md](./Capture/README.md) |
| `HistoryExchange/` | Cola HC + External |
| `LegalRecord/` | Export registro legal |
| `Specialty/` | Odontología / oftalmología / inpatient aux |
| `Home/` | Plugin BC: panel + `Sections/` (`*SectionProvider`) |

Catálogos UI del asistente: `*/Application/*UiActionCatalog` por módulo (Encounter, CarePlan, Lab, …), registrados en `product-registries.php`.

```text
<Modulo>/Application/{Authorization,Flows,Agents,Presentation}/
<Modulo>/Domain/{Model,…}
<Modulo>/Infrastructure/{External,Persistence}/
```

Infra técnica compartida: `components/Shared/`. Scheduling / Terminology / Person son **otros BCs**.

Modelos AR: `common/models/Clinical/` (ancla en cada módulo: `Infrastructure/Persistence/README.md`).
