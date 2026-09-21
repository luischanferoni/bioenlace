# Domain Model (Capture)

Aggregates y value objects **sin I/O ni Yii**.

Norte: [ddd-norte-modelo-rico.md](../../../../../../docs/decisions/ddd-norte-modelo-rico.md).  
Estado del módulo: [../README.md](../README.md).

## Hoy

| Tipo | Clase |
|------|--------|
| Aggregate | `ClinicalCapture` — etapas, transcript, extracción, audio, resoluciones, completar/descartar |
| VO / id | `ClinicalCaptureId`, `ClinicalCaptureStage` |
| Factory issues | `ClinicalCaptureIssueFactory` |

## Próximo

- Tipar documentación extraída (filas por categoría) y resoluciones como VO.
- Mover cuerpos de etapa desde `Pipeline/ClinicalCapturePipelineSupport` a cada use case.