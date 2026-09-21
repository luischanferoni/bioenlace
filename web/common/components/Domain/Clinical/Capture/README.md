# Capture (`Domain/Clinical/Capture`)

Capacidad: **intake de documentación clínica** — texto/audio → extracción → issues → checkpoint.

**Norte:** [ddd-norte-modelo-rico.md](../../../../../docs/decisions/ddd-norte-modelo-rico.md) — **un eje por nivel**.  
**Gramática:** [domain-folder-grammar.md](../../../../../docs/decisions/domain-folder-grammar.md).

## Límites

| Sí | No |
|----|-----|
| Flujo `/captura/*`, STT, post-proceso, issues | Guardar nota FHIR → Encounter Documentation |

## Eje (no desviarse)

| Nivel | Eje | En Capture |
|-------|-----|------------|
| `Application/*` | Capacidad / lenguaje | `Checkpoint/`, `Extraction/`, `Definition/`, `RowContract/` |
| `Application/<Capacidad>/*` | Rol CA o clases de la capacidad | `UseCase/`, `Presentation/` **dentro** de la capacidad |
| `Domain/*` | Building block | `Model/`, `Catalog/`, `Policy/`, `Port/`, `RowContract/` |
| `Infrastructure/*` | Adapter | `Persistence/`, `SpeechToText/`, … |

**Prohibido:** `Application/UseCase/` o `Application/Presentation/` hermanos de `Checkpoint/`.  
**Prohibido:** `Support/`, `Helpers/`, facades legacy.

## Forma

```text
Application/
  Checkpoint/
    UseCase/              # CreateOrUpload, Transcribe, Save, Discard, List, View, ResolveAudio, ApplyResolutions
    Presentation/         # ClinicalCapturePresenter
    ClinicalCaptureCheckpoint.php
  Extraction/
    UseCase/              # AnalyzeClinicalNote, AnalyzeClinicalCaptureDraft
    ConsultaProcesamientoService.php, post-proceso, knobs, …
  Definition/
  RowContract/

Domain/
  Catalog/ | Model/ | RowContract/ | Policy/ | Port/

Infrastructure/
  Persistence/ | SpeechToText/ | Terminology/ | Logging/ | PedidoAtencion/
```

Controller API → `Application/<Capacidad>/UseCase/*` (sin facade intermedia).

## Deuda

- Tipologías Domain residuales si aparecen.
- Adapter derivación ↔ AR `Servicio` (Infrastructure).

## Referencias

- Encounter Documentation: `Encounter/Application/Documentation/`
- Producto: [captura-clinica.md](../../../../../docs/producto/captura-clinica.md)
