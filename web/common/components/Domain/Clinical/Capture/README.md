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
  CreateOrUploadClinicalCapture, TranscribeClinicalCapture,
  AnalyzeClinicalCaptureDraft, SaveClinicalCapture,
  ApplyClinicalCaptureResolutions, DiscardClinicalCapture,
  ListClinicalCaptures, ViewClinicalCapture, ResolveClinicalCaptureAudio
  AnalyzeClinicalNote          # análisis suelto (sin checkpoint)
  EncounterCapturePipelineService  # facade compat → use cases
  Pipeline/ClinicalCapturePipelineSupport  # helpers compartidos
Domain/Model|Policy|Port
Infrastructure/Logging|Persistence|SpeechToText|Terminology
```

## Oleadas

| Oleada | Estado |
|--------|--------|
| 1–3b | hechas (Domain rico, Documentation en Encounter, pipeline vía aggregate) |
| 4 | hecha — use cases por etapa; controller apunta a ellos; Support interno |
| 4b | hecha — cuerpos de etapa en cada use case; Support solo helpers |
| 5 | hecha — Domain Policy sin Platform; knobs vía `EncounterCapturePostProcessKnobs` |
| 5b | parcial — VO `ClinicalCaptureResolution` en Applier; filas/`*Input` pendientes |

## Deuda restante

- Completitud vía `*Input` en models/ (sustituir por contratos/VO de fila).
- VO tipados para filas extraídas (resoluciones: hecho el VO base).

## Referencias

- Persistencia: `Encounter/Application/Documentation/EncounterDocumentationService`
- Producto: [captura-clinica.md](../../../../../docs/producto/captura-clinica.md)
