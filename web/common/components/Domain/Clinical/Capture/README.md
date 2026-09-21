# Capture (`Domain/Clinical/Capture`)

Capacidad de producto: **intake de documentación clínica**.

**Norte:** [ddd-norte-modelo-rico.md](../../../../../docs/decisions/ddd-norte-modelo-rico.md) · [domain-folder-grammar.md](../../../../../docs/decisions/domain-folder-grammar.md)

## Naming: `ClinicalCapture` vs `EncounterCapture`

| Nombre | Qué es |
|--------|--------|
| **`ClinicalCapture`** | Aggregate / lenguaje del módulo Capture (Domain + Application) |
| **`EncounterCapture`** | ActiveRecord / tabla `encounter_capture` (`common/models/Clinical/`) — detalle de persistencia |
| **`EncounterDefinition*`** | Maestro de plantilla de categorías del acto (no es el checkpoint) |

Application y Domain hablan **`ClinicalCapture*`**. El AR se llama `EncounterCapture` por la tabla; Infrastructure lo traduce.

## `Application/Presentation/` ≠ controllers

| Pieza | Dónde | Rol |
|-------|--------|-----|
| **Controller HTTP** | `frontend/modules/api/v1/...` | Request/response HTTP, permisos de ruta |
| **`Presentation/*Presenter`** | `Capture/Application/Presentation/` | Forma el **payload** (ok/fail/`toApiArray`) que el controller serializa |

No hay HTML ni rutas Yii aquí. Es el adapter de salida CA.

## Mapa `Application/` (qué hace cada clase)

### Roles CA

| Clase | Carpeta | Hace |
|-------|---------|------|
| `CreateOrUploadClinicalCapture`, `Transcribe…`, `Analyze…`, `Save…`, … | `UseCase/` | Una intención HTTP = una clase |
| `ClinicalCapturePresenter` | `Presentation/` | Arma arrays de respuesta del checkpoint |

### Colaboradores (`Application/Service/`)

| Clase | Hace |
|-------|------|
| `ClinicalCaptureCheckpoint` | Lookup/persist del aggregate, audio en disco; delega forma a Presenter |
| `ClinicalCaptureAnalysisService` | Orquesta análisis IA de la nota (ex `ConsultaProcesamientoService`) |
| `ClinicalCaptureTextNormalizer` | Normaliza texto clínico antes de IA |
| `ClinicalCaptureExtractionPostProcessor` | Post-proceso de `datosExtraidos` según policy Domain |
| `ClinicalCaptureClinicalTermValidator` | ¿El término extraído es plausible? |
| `ClinicalCapturePostProcessKnobs` | Pasa overrides YAML Platform → policy Domain |
| `ClinicalCaptureLlmConfidenceService` | Heurísticas de confianza ortográfica LLM |
| `ClinicalCaptureCategoryResolver` | Qué categorías entran al prompt (definición + actor) |
| `ClinicalCaptureOperationalContextResolver` | PES / servicio / encounter class desde body+sesión |
| `EncounterDefinitionBootstrapService` | Asegura fila `encounter_definition` |
| `EncounterDefinitionWorkflowSanitizer` | Limpia `workflow_json` legacy |
| `ClinicalCaptureRowContracts` | Factory/wiring del port de contratos de fila |
| `CompositeClinicalCaptureRowContractRegistry` | Registry Domain + fallback Yii |
| `ClinicalCaptureResolutionApplier` | Aplica resoluciones del profesional a filas |

## Forma

```text
Application/
  UseCase/
  Presentation/
  Service/     # *Service / *Resolver / *Applier / Checkpoint / wiring

Domain/
  Model/ClinicalCapture.php
  Catalog/ | RowContract/ | Policy/ | Port/

Infrastructure/
  Persistence/   # AR EncounterCapture ↔ aggregate ClinicalCapture
  …
```

## Límites

| Sí | No |
|----|-----|
| `/captura/*`, STT, extracción, issues | Guardar nota FHIR → Encounter Documentation |
