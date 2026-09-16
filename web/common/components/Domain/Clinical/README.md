# Clinical (`common/components/Domain/Clinical`)

Bounded context FHIR clínico. **Eje = módulo de capacidad** (sin `Shared/` ni `Enum/` basurero).

Plan: [`clinical-modulos-capacidad`](../../../docs/plans/clinical-modulos-capacidad/).

## Módulos

| Módulo | Dueño |
|--------|--------|
| `Encounter/` | Status/enums núcleo, catalogs journey/motivos, services journey, flows `atencion.*` |
| `Emergency/` | Guardia; Domain enums; flows `urgencias.*` |
| `Inpatient/` | Internación; agents alta/cama; flows `internacion.*` |
| `Laboratory/` | Lab + agents + flows `laboratorio.*` + ACL External |
| `Prescription/` | Receta + RDI agent + flows `receta.*` |
| `CarePlan/` | Enums/status care plan; flows `tratamiento.*` |
| `CareCohort/` | Care packs + followup agent; flows `care-packs.*` |
| `PedidoAtencion/` | Catálogo + servicios línea×acto |
| `HistoryExchange/` | Cola HC + IntegrationRetry agent |
| `Specialty/` | Odontología / oftalmología |
| `Infrastructure/External/` | ACL LIS / receta / HC nacional |
| `Assistant/` `Home/` `DataAccess/` | Plugins motor Platform |
| `Service/` `Workflow/` `Capture/` `Text/` … | Legacy a reclasificar |

Forma por módulo:

```text
<Modulo>/Application/{Flows,Agent}/
<Modulo>/Domain/
<Modulo>/Infrastructure/   # si aplica
<Modulo>/Service/          # mientras migra
```

Modelos AR: `common/models/Clinical/`.
