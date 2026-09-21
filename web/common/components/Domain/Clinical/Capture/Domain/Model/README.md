# Domain Model (Capture)

Aggregates y value objects **sin I/O ni Yii**.

Norte: [ddd-norte-modelo-rico.md](../../../../../../docs/decisions/ddd-norte-modelo-rico.md).  
Estado del módulo: [../README.md](../README.md).

## Hoy

| Tipo | Clase |
|------|--------|
| Aggregate | `ClinicalCapture` — etapas, transcript, extracción, completar/descartar |
| VO / id | `ClinicalCaptureId`, `ClinicalCaptureStage` |
| Factory issues | `ClinicalCaptureIssueFactory` |

## Próximo

- Tipar documentación extraída (filas por categoría) y resoluciones como VO.
- Más mutaciones del pipeline vía aggregate (crear/subir audio, guardar completo).
