# Gramática de carpetas y sufijos en Domain/

**Estado:** aceptado.  
**Norte (cero ambigüedad de ejes):** [ddd-norte-modelo-rico.md](./ddd-norte-modelo-rico.md).

## Regla de empaquetado

1. **Módulo** = capacidad de producto (`Capture/`, `Encounter/`).
2. **L1 del módulo** = capas `Application/` · `Domain/` · `Infrastructure/`.
3. **`Application/*`** = **solo roles Clean Architecture / plugins** (técnico):  
   `UseCase/`, `Presentation/`, `Service/`, `Authorization/`, `Flows/`, `Agents/`.  
   El dominio va en el **nombre de la clase**.
4. **`Domain/*`** = building blocks (`Model/`, `Catalog/`, `Policy/`, `Port/`, `RowContract/`).
5. **`Infrastructure/*`** = adapters.

**Prohibido bajo `Application/`:** carpetas con nombre de capacidad/dominio (`Checkpoint/`, `Extraction/`, `Definition/`, `RowContract/`, `Support/`, …).

## Esqueleto Clinical

```text
Domain/Clinical/<Modulo>/
  Application/
    UseCase/                    # interactors (* nombre de dominio en la clase)
    Presentation/               # *Presenter / *PresentationService
    Service/                    # *Service / *Resolver / *Applier / Checkpoint / wiring
    Authorization/ | Flows/ | Agents/   # plugins si aplican
  Domain/
    Model/ | Catalog/ | Policy/ | Port/ | RowContract/ | …
  Infrastructure/
    External/ | Persistence/ | <Adapter>/…
```

## Referencia — Capture

```text
Capture/Application/
  UseCase/                 # CreateOrUpload, Transcribe, Analyze*, Save, …
  Presentation/            # ClinicalCapturePresenter
  Service/                 # ClinicalCaptureCheckpoint, *AnalysisService, *RowContracts, …
Capture/Domain/
  Model/ | Catalog/ | RowContract/ | Policy/ | Port/
```

## Sufijos → carpeta

| Sufijo | Carpeta |
|--------|---------|
| Interactor | `Application/UseCase/` |
| `*Presenter`, `*PresentationService` | `Application/Presentation/` |
| `*Service` / resolvers / appliers Application | `Application/Service/` |
| `*Access` | `Application/Authorization/` |
| `*Agent`, `*AgentPolicy` | `Application/Agents/` |
| Aggregate | `Domain/Model/` |
| `*Catalog`, policies | `Domain/Catalog/`, `Domain/Policy/` |
| `*RowContract` | `Domain/RowContract/` (wiring Application en `Application/Service/`, no carpeta `RowContract/` bajo Application) |
| ACL | `Infrastructure/External/…` |
| AR Yii | `common/models/<BC>/` |

## Relacionado

- [ddd-norte-modelo-rico.md](./ddd-norte-modelo-rico.md)
- [clinical-modulos-capacidad.md](./clinical-modulos-capacidad.md)
- `Capture/README.md`
