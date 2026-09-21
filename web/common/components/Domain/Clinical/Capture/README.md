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
  CreateOrUploadClinicalCapture, TranscribeClinicalCapture, …
  EncounterCapturePipelineService  # facade compat → use cases
  Pipeline/ClinicalCapturePipelineSupport  # helpers compartidos
  Text/EncounterCapturePostProcessKnobs    # inyecta knobs Platform → Domain Policy
Domain/Model|Policy|Port
  Port/ClinicalCaptureRowContractRegistry  # completitud/resoluciones sin *Input en Policy
Infrastructure/…/YiiModelClinicalCaptureRowContractRegistry  # adapter *Input/models
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

## Deuda restante

- Semántica de fila aún en `*Input` / tipologías `models/Clinical` (mover a Domain VO cuando se toque cada tipología).
- Default del registry en Domain Policy apunta a Infrastructure (transitorio).

## Referencias

- Persistencia: `Encounter/Application/Documentation/EncounterDocumentationService`
- Producto: [captura-clinica.md](../../../../../docs/producto/captura-clinica.md)
