# Clinical (`common/components/Domain/Clinical`)

Bounded context FHIR clínico. **Eje L1 = módulo de capacidad** (sin Shared / Enum / Service catch-all).

ADR: [clinical-modulos-capacidad](../../../docs/decisions/clinical-modulos-capacidad.md), [domain-folder-grammar](../../../docs/decisions/domain-folder-grammar.md), [ddd-norte-modelo-rico](../../../docs/decisions/ddd-norte-modelo-rico.md) (**Application = rol CA; dominio en clases**).

## Módulos

| Módulo | Contenido |
|--------|-----------|
| `Encounter/` | Núcleo + Documentation |
| `Emergency/` | Guardia |
| `Inpatient/` | Internación |
| `Laboratory/` | Lab + External LIS |
| `Prescription/` | Receta |
| `CarePlan/` | Planes / órdenes |
| `CareCohort/` | Care packs |
| `PedidoAtencion/` | Línea×acto |
| `Capture/` | Intake — piloto de packaging Application ([README](./Capture/README.md)) |
| `HistoryExchange/` | Cola HC |
| `LegalRecord/` | Export legal |
| `Specialty/` | Odontología / oftalmología / aux |
| `Home/` | Plugin panel |

```text
<Modulo>/Application/{UseCase,Presentation,Service,Authorization,Flows,Agents,Seed}/
<Modulo>/Domain/{Model,Catalog,Policy,Port,RowContract,…}/
<Modulo>/Infrastructure/{External,Persistence,<Adapter>}/
```

Bajo `Application/`: **prohibido** carpetas de capacidad (`Checkpoint/`, `Extraction/`, …) y PHP suelto. Dominio en el nombre de clase.

Infra compartida: `components/Shared/`. AR: `common/models/Clinical/`.
