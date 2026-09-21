# Gramática de carpetas y sufijos en Domain/

**Estado:** aceptado.  
**Norte:** [ddd-norte-modelo-rico.md](./ddd-norte-modelo-rico.md).

## Regla de empaquetado

1. **Módulo** = capacidad de producto (`Capture/`, `Encounter/`).
2. **L1** = `Application/` · `Domain/` · `Infrastructure/`.
3. **`Application/*`** = solo roles CA: `UseCase/`, `Presentation/`, `Service/`, `Authorization/`, `Flows/`, `Agents/`.
4. **`Domain/*`** = building blocks (`Model/`, `Catalog/`, `Policy/`, `Port/`, `RowContract/`).
5. **`Infrastructure/*`** = adapters.

**Prohibido bajo `Application/`:** carpetas de capacidad/dominio (`Checkpoint/`, `Extraction/`, …).

## Sufijos = solo técnicos transversales

Patrón de clase: `[<Contexto>]<Concepto><SufijoTécnico>`.

El **sufijo** pertenece a un **catálogo cerrado** (CA/DDD) y se reutiliza en todo el proyecto. No inventar metáforas locales (`Normalizer`, `Sanitizer`, `PostProcessor`, `Overrides`, `Checkpoint`, `Validator` en Application).

| Sufijo | Carpeta |
|--------|---------|
| Verb phrase | `Application/UseCase/` |
| `*Presenter` | `Application/Presentation/` |
| `*Service` | `Application/Service/` |
| `*Resolver` | `Application/Service/` |
| `*Applier` | `Application/Service/` |
| `*Access` | `Application/Authorization/` |
| `*Agent`, `*AgentPolicy` | `Application/Agents/` |
| Aggregate / VO | `Domain/Model/` |
| `*Catalog` | `Domain/Catalog/` |
| `*Policy` | `Domain/Policy/` |
| `*Repository` / `*Port` / `*Registry` | `Domain/Port/` |
| `*RowContract` | `Domain/RowContract/` |
| ACL `*Connector` / `*Mapper` | `Infrastructure/External/…` |
| AR Yii | `common/models/<BC>/` |

## Esqueleto Clinical

```text
Domain/Clinical/<Modulo>/
  Application/
    UseCase/ | Presentation/ | Service/
  Domain/
    Model/ | Catalog/ | Policy/ | Port/ | RowContract/
  Infrastructure/
    External/ | Persistence/ | <Adapter>/…
```

## Referencia — Capture

```text
Capture/Application/
  UseCase/SaveCapture.php
  Presentation/CapturePresenter.php
  Service/CaptureDraftService.php
  Service/ExtractionPostProcessService.php
Capture/Domain/
  Model/ClinicalCapture.php
  Policy/ExtractedTermPolicy.php
  Policy/CaptureCompletenessPolicy.php
```

## Relacionado

- [ddd-norte-modelo-rico.md](./ddd-norte-modelo-rico.md)
- `Capture/README.md`
