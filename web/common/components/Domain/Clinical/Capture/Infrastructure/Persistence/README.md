# Persistence (Capture)

Los ActiveRecord Yii del módulo **no** viven aquí. Fuente: `web/common/models/Clinical/` (`EncounterCapture`, `EncounterCaptureAnalysis`, `EncounterDefinition`, …).

Aquí: adapters de persistencia / cache de análisis (`EncounterCaptureAnalysisCache`) que implementan el borde Infrastructure del intake.

Norte: [ddd-norte-modelo-rico.md](../../../../../../docs/decisions/ddd-norte-modelo-rico.md).
