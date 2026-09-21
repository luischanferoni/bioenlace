# Capture (`Domain/Clinical/Capture`)

Capacidad de producto: **intake de documentación clínica**.

**Norte (cero ambigüedad):** [ddd-norte-modelo-rico.md](../../../../../docs/decisions/ddd-norte-modelo-rico.md)  
**Gramática:** [domain-folder-grammar.md](../../../../../docs/decisions/domain-folder-grammar.md)

## Eje bajo `Application/` (no negociable)

**Técnico = rol Clean Architecture.** Dominio = nombre de clase / `Domain/`.

| Permitido bajo `Application/` | Prohibido bajo `Application/` |
|-------------------------------|-------------------------------|
| `UseCase/` | `Checkpoint/`, `Extraction/`, `Definition/` |
| `Presentation/` | `RowContract/`, `Support/`, `Helpers/` |
| `*.php` en raíz (services/resolvers; dominio en el nombre) | Cualquier carpeta con nombre de capacidad de negocio |

## Forma

```text
Application/
  UseCase/                 # intenciones HTTP (dominio en el nombre de clase)
  Presentation/            # ClinicalCapturePresenter
  ClinicalCaptureCheckpoint.php
  ConsultaProcesamientoService.php
  ClinicalCaptureRowContracts.php
  EncounterCaptureCategoryResolver.php
  …

Domain/
  Model/ | Catalog/ | RowContract/ | Policy/ | Port/

Infrastructure/
  Persistence/ | SpeechToText/ | Terminology/ | Logging/ | …
```

Controller API → `Application/UseCase/*`.

## Límites

| Sí | No |
|----|-----|
| `/captura/*`, STT, extracción, issues | Guardar nota FHIR → Encounter Documentation |

## Deuda

- Tipologías Domain residuales si aparecen.
- Adapter derivación ↔ AR `Servicio` (Infrastructure).
