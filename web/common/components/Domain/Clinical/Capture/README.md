# Capture (`Domain/Clinical/Capture`)

Capacidad de producto: **intake de documentación clínica**.

**Norte:** [ddd-norte-modelo-rico.md](../../../../../docs/decisions/ddd-norte-modelo-rico.md) · [domain-folder-grammar.md](../../../../../docs/decisions/domain-folder-grammar.md)

## Naming

| Nombre | Qué es |
|--------|--------|
| **`ClinicalCapture`** | Aggregate (y VOs/ports anclados: Id, Stage, Repository) |
| **`Capture*`** (resto Application/Domain) | Colaboradores del módulo — sin repetir `Clinical`+`Capture` |
| **`EncounterCapture`** | ActiveRecord / tabla `encounter_capture` — solo Infra / `common/models` |
| **`EncounterDefinition*`** | Maestro de plantilla de categorías del acto |

Patrón: `[<Contexto>]<Concepto><SufijoTécnico>` — **solo** sufijos del catálogo transversal CA/DDD.

## `Application/Presentation/` ≠ controllers

| Pieza | Dónde | Rol |
|-------|--------|-----|
| **Controller HTTP** | `frontend/modules/api/v1/...` | Request/response HTTP, permisos de ruta |
| **`CapturePresenter`** | `Application/Presentation/` | Payload ok/fail/`toApiArray` |

## Mapa `Application/`

### UseCase/ (verb phrase)

| Clase | Hace |
|-------|------|
| `CreateOrUploadCapture` | Crea/reabre draft; audio/texto inicial |
| `TranscribeCapture` | STT servidor sobre audio del draft |
| `AnalyzeCaptureDraft` | Extracción IA del transcript del draft |
| `AnalyzeClinicalNote` | Extracción IA de nota suelta (sin draft) |
| `ApplyCaptureResolutions` | Resoluciones del profesional sobre filas |
| `SaveCapture` | Draft → Encounter Documentation |
| `ViewCapture` / `ListCaptures` / `DiscardCapture` / `ResolveCaptureAudio` | Lectura / lista / discard / audio |

### Presentation/

| Clase | Hace |
|-------|------|
| `CapturePresenter` | Arma arrays de respuesta del draft |

### Service/

| Clase | Hace |
|-------|------|
| `CaptureDraftService` | Lookup/persist aggregate, audio; delega forma a Presenter |
| `CaptureExtractionService` | Orquesta extracción IA (STT → LLM → post-proceso); no guarda Encounter |
| `CaptureTextService` | Limpieza local pre-IA |
| `ExtractionPostProcessService` | Post-proceso `datosExtraidos` + knobs Platform → Domain Policy |
| `LlmConfidenceService` | Heurísticas de confianza ortográfica LLM |
| `CaptureCategoryResolver` | Categorías del prompt (definición + actor) |
| `OperationalContextResolver` | PES / servicio / encounter class |
| `EncounterDefinitionBootstrapService` | Asegura fila `encounter_definition` |
| `EncounterDefinitionWorkflowService` | Limpia `workflow_json` legacy |
| `RowContractService` | Wiring registry / completeness / applier |
| `CompositeRowContractRegistry` | Registry Domain + fallback Yii |
| `ResolutionApplier` | Aplica resoluciones tipadas a filas |

### Domain (policies relevantes)

| Clase | Hace |
|-------|------|
| `ExtractedTermPolicy` | Plausibilidad de términos extraídos (`TerminologyLookupPort`) |
| `ExtractionPostProcessPolicy` | Semántica filter/backfill/relocate/lexicon |
| `CaptureCompletenessPolicy` | Completitud vs categorías requeridas |

## Forma

```text
Application/
  UseCase/
  Presentation/
  Service/

Domain/
  Model/ClinicalCapture.php
  Catalog/ | RowContract/ | Policy/ | Port/
```

## Límites

| Sí | No |
|----|-----|
| `/captura/*`, STT, extracción, issues | Guardar nota FHIR → Encounter Documentation |
