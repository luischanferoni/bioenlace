# Capture (`Domain/Clinical/Capture`)

Capacidad: **intake de documentación clínica** — texto/audio → normalización → extracción → issues resolubles → checkpoint de captura.

Norte: [ddd-norte-modelo-rico.md](../../../../../docs/decisions/ddd-norte-modelo-rico.md).

## Límites

| Sí (Capture) | No |
|--------------|-----|
| Pipeline `/captura/*`, STT, post-proceso, issues | Guardar nota FHIR → `Encounter/Application/Documentation/` |

## Forma

```text
Application/
  ClinicalCaptureRowContracts              # wiring registry/validator/applier
  Text/EncounterCapturePostProcessKnobs
  Pipeline/ClinicalCapturePipelineSupport
  use cases + facade…
Domain/Catalog|Model|Policy|Port
  Catalog/EncounterCaptureActorCatalog, EncounterDefinitionWorkflowCatalog, EncounterClassCodes
  Port/ClinicalCaptureRowContractRegistry
Infrastructure/…/YiiModelClinicalCaptureRowContractRegistry
```

## Oleadas

| Oleada | Estado |
|--------|--------|
| 1–3b | hechas (Domain rico, Documentation en Encounter, pipeline vía aggregate) |
| 4 | hecha — use cases por etapa; controller apunta a ellos; Support interno |
| 4b | hecha — cuerpos de etapa en cada use case; Support solo helpers |
| 5 | hecha — Domain Policy sin Platform; knobs vía `EncounterCapturePostProcessKnobs` |
| 5b | hecha — VO `ClinicalCaptureResolution` en Applier |
| 6 | hecha — completitud/resoluciones vía port `ClinicalCaptureRowContractRegistry` (adapter Yii/`*Input`) |
| 6b | hecha — Domain Policy sin default Infra; factory Application; catálogos en `Domain/Catalog` |

## Deuda restante

- Semántica de fila aún en `*Input` / tipologías `models/Clinical` (mover a Domain VO cuando se toque cada tipología).
- `EncounterDefinitionWorkflowCatalog::templateForServicio` aún tipa AR `Servicio` (param de Application).

## Referencias

- Persistencia: `Encounter/Application/Documentation/EncounterDocumentationService`
- Producto: [captura-clinica.md](../../../../../docs/producto/captura-clinica.md)
