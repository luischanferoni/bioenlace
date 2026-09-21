# Gramática de carpetas y sufijos en Domain/

**Estado:** aceptado e implantado (Clinical + BCs Content/Geo/Terminology/Organization/Person/Scheduling).  
**Norte de contenido de capas:** [ddd-norte-modelo-rico.md](./ddd-norte-modelo-rico.md) — si packaging y responsabilidad chocan, gana el norte.

## Contexto

La gramática previa mezclaba `Service/` (casos de uso) con capas DDD de libro y admitía L1 opcionales (`Dto/`, `Presentation/`) más carpetas ad-hoc (`Legacy/`, `PatientSummary/`, …). Eso producía módulos con carpetas de más y de menos.

Referencia: capas Vernon / eShop / Clean Architecture (`application` · `domain` · `infrastructure`). En Bioenlace, `Service/` L1 equivale a **Application Services**, no a Domain Services de Evans. Preferir `Application/UseCase/` cuando el módulo tiene varias intenciones (piloto Capture).

## Decisión

### Clinical — módulo de capacidad (esqueleto exacto)

```text
Domain/Clinical/<Modulo>/
  Application/
    UseCase/                    # opcional; interactors (piloto Capture)
    Authorization/              # *Access
    Flows/intents/…             # metadata asistente (Bioenlace)
    Agents/                     # *Agent / *AgentPolicy
    Presentation/               # *Presenter / *PresentationService (no HTTP Yii)
    <Capacidad>/…               # subáreas por lenguaje ubicuo (ver eje abajo)
  Domain/
    Model/                      # Aggregates (reglas puras; sin Yii/DB)
    Catalog/ | Policy/ | Port/ | RowContract/ | …   # lenguaje del módulo
  Infrastructure/
    External/<Sistema>/…        # Connector|Contract|Dto|Exception|Mapper|Registry
    Persistence/
      README.md                 # AR Yii → common/models/Clinical/ (no duplicar)
    <Adapter>/…                 # p.ej. SpeechToText/, Terminology/, Logging/
```

Plugins del BC en raíz Clinical cuando aplican: `Home/`, `DataAccess/` (y `Assistant/` solo si hay hydrators/hints del motor). Misma tríada `Application/` · `Domain/` · `Infrastructure/`; además `Home/Sections/` para `*SectionProvider`. Catálogos UI del asistente: `*/Application/*UiActionCatalog` por módulo de capacidad (no un cajón Clinical/Assistant).

Controllers API y `views/json`: `frontend/modules/api/v1/…` (borde Presentation del deploy; **fuera** de `components/Domain`).

**Prohibido en L1 del módulo:** `Service/`, `Dto/`, `Presentation/`, `Legacy/`, `Support/`, `Mapper/`, `Batch/`, `PatientSummary/`, `AiContext/`, `Workflow/`, `Checkpoint/` (como L1), PHP suelto en raíz del módulo o del BC; `Clinical/Infrastructure/` en raíz del BC; `Application/Legacy/`.

Todo L1 ad-hoc se reubica bajo `Application/`, `Domain/` o `Infrastructure/`.  
`Checkpoint/`, `Extraction/`, `Definition/`, etc. **sí** pueden vivir **dentro** de `Application/` cuando son lenguaje ubicuo del módulo (no L1).

### Eje de subcarpetas dentro de cada capa

Mismo criterio que el norte ([§4 ddd-norte-modelo-rico](./ddd-norte-modelo-rico.md)):

1. **Capacidad / lenguaje ubicuo** del módulo.
2. **No** cajones técnicos: `Support/`, `Helpers/`, `Shared/`, `Utils/`, `Pipeline/` genérico.
3. Roles CA estables bajo `Application/`: `UseCase/`, `Presentation/`, `Authorization/`, `Flows/`, `Agents/`.

**Referencia implantada — Capture:**

```text
Capture/Application/
  UseCase/          # CreateOrUpload, Transcribe, Analyze, Save, …
  Checkpoint/       # lookup / persist / audio del checkpoint
  Presentation/     # ClinicalCapturePresenter (ok/fail/toApiArray)
  Extraction/       # texto + post-proceso + ConsultaProcesamientoService
  RowContract/      # wiring Domain RowContract + ResolutionApplier
  Definition/       # EncounterDefinition / categorías / bootstrap
```

### BC chico / mediano

Misma tríada `Application/` · `Domain/` · `Infrastructure/` en la raíz del BC (sin módulo), más plugins `Assistant/` | `Home/` | `DataAccess/` si aplican. Áreas de lenguaje (`Ventanilla`, `Quirofano`, `Representation`, …) con las mismas tres capas internas.

### Sufijos → carpeta

| Sufijo | Carpeta |
|--------|---------|
| Caso de uso (interactor) | `Application/UseCase/` (preferido) o `Application/` como `*Service` fino |
| `*Service` (orquestación Application, no multi-pipeline) | `Application/` o subcarpeta de **capacidad** |
| `*Access` | `Application/Authorization/` |
| `*Catalog`, enums, policies | `Domain/` (`Catalog/`, `Policy/`, …) |
| `*CatalogService` | `Application/` |
| `*Agent`, `*AgentPolicy` | `Application/Agents/` |
| `*Presenter`, `*PresentationService` | `Application/Presentation/` |
| Aggregate / entity rica | `Domain/Model/…` |
| `*RowContract` (integridad de fila) | `Domain/RowContract/` (+ wiring en `Application/RowContract/` si aplica) |
| `*FlowDraftHydrator` | `Assistant/` (plugin BC) |
| `*Connector` / `*Mapper` / `*Registry` (ACL) | `Infrastructure/External/…` |
| `*SectionProvider` | `Home/Sections/` |
| ActiveRecord Yii | `common/models/<BC>/` (ancla: `Infrastructure/Persistence/README`) |

Lenguaje ubicuo en español en **nombres de clase** se conserva. Carpetas técnicas en inglés.

### Aggregates (`Domain/Model`)

- Sin I/O ni Yii: Application reconstituye desde AR, aplica mutaciones y persiste.
- Piloto vivo en **Encounter**: `Encounter` (`open`/`finish`/`cancel`), `Condition` (`transitionTo`), VO `ChiefComplaintReason`.
- Piloto **Capture**: aggregate `ClinicalCapture` + ports; use cases por etapa.
- Extender reglas a más módulos es trabajo continuo: mover invariantes desde Application al aggregate correspondiente.

### Forma implantada (resumen)

| Ámbito | Resultado |
|--------|-----------|
| Clinical (módulos) | Tríada + `Application/Agents/`; sin `Service/` L1 |
| Capture (piloto Application) | `UseCase/` + capacidades (`Checkpoint/`, `Extraction/`, `Definition/`, `RowContract/`) + `Presentation/`; sin facade legacy |
| Content, Geo, Terminology | Tríada; Terminology ACL Snowstorm bajo External |
| Organization, Person, Scheduling | Tríada; Person Representation/Ventanilla y Scheduling Quirofano como áreas |
| Captura IA | `ConsultaProcesamientoService` en `Capture/Application/Extraction/`; logger en `Capture/Infrastructure/Logging/` |

## Relacionado

- [clinical-modulos-capacidad.md](./clinical-modulos-capacidad.md)
- [shared-top-level-infrastructure.md](./shared-top-level-infrastructure.md)
- [ddd-bounded-contexts-capas-y-metadata.md](./ddd-bounded-contexts-capas-y-metadata.md)
- **Norte de diseño (modelo rico):** [ddd-norte-modelo-rico.md](./ddd-norte-modelo-rico.md)
- Guía Capture: `web/common/components/Domain/Clinical/Capture/README.md`
- `Domain/README.md`, `Clinical/README.md`
- Test de forma: `BoundedContextLayerShapeTest`
