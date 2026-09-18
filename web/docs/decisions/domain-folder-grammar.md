# Gramática de carpetas y sufijos en Domain/

**Estado:** aceptado (oleada Clinical Application/Domain/Infrastructure).

## Contexto

La gramática previa mezclaba `Service/` (casos de uso) con capas DDD de libro y admitía L1 opcionales (`Dto/`, `Presentation/`) más carpetas ad-hoc (`Legacy/`, `PatientSummary/`, …). Eso producía módulos con carpetas de más y de menos.

Referencia: capas Vernon / eShop (`application` · `domain` · `infrastructure`). En Bioenlace, `Service/` L1 equivale a **Application Services**, no a Domain Services de Evans.

## Decisión

### Clinical — módulo de capacidad (esqueleto exacto)

```text
Domain/Clinical/<Modulo>/
  Application/
    Authorization/              # *Access
    Flows/intents/…             # metadata asistente (Bioenlace)
    Agents/                     # *Agent / *AgentPolicy
    Presentation/               # *Presenter / *PresentationService (no HTTP Yii)
    …                           # *Service de caso de uso; Dto/; subáreas
  Domain/
    Model/                      # Aggregates (stubs permitidos durante migración)
    …                           # *Catalog, enums, policies tipadas
  Infrastructure/
    External/<Sistema>/…        # Connector|Contract|Dto|Exception|Mapper|Registry
    Persistence/
      README.md                 # AR Yii → common/models/Clinical/ (no duplicar)
```

Plugins del BC **solo** en raíz Clinical: `Assistant/`, `Home/`, `DataAccess/`.

Controllers API y `views/json`: `frontend/modules/api/v1/…` (borde Presentation del deploy; **fuera** de `components/Domain`).

**Prohibido en L1 del módulo:** `Service/`, `Dto/`, `Presentation/`, `Legacy/`, `Support/`, `Mapper/`, `Batch/`, `PatientSummary/`, `AiContext/`, `Workflow/`, PHP suelto en raíz del módulo o del BC; `Clinical/Infrastructure/` en raíz del BC.

Todo L1 ad-hoc se reubica bajo `Application/`, `Domain/` o `Infrastructure/`.

### BC chico / mediano

Misma tríada `Application/` · `Domain/` · `Infrastructure/` en la raíz del BC (sin módulo), más plugins `Assistant/` | `Home/` | `DataAccess/` si aplican. Áreas de lenguaje (`Ventanilla`, `Quirofano`, …) con las mismas tres capas internas.

### Sufijos → carpeta

| Sufijo | Carpeta |
|--------|---------|
| `*Service` (caso de uso) | `Application/` |
| `*Access` | `Application/Authorization/` |
| `*Catalog`, enums, policies | `Domain/` |
| `*CatalogService` | `Application/` |
| `*Agent`, `*AgentPolicy` | `Application/Agents/` |
| `*Presenter`, `*PresentationService` | `Application/Presentation/` |
| Aggregate / entity rica | `Domain/Model/…` |
| `*FlowDraftHydrator` | `Assistant/` (plugin BC) |
| `*Connector` / `*Mapper` / `*Registry` (ACL) | `Infrastructure/External/…` |
| `*SectionProvider` | `Home/Sections/` |
| ActiveRecord Yii | `common/models/<BC>/` (ancla: `Infrastructure/Persistence/README`) |

Lenguaje ubicuo en español en **nombres de clase** se conserva. Carpetas técnicas en inglés.

### Migración

Plan: `docs/plans/clinical-ddd-folder-grammar/`. Piloto carpetas: módulo `Encounter/`. Fases 2–4 hechas (Agents + tríada Clinical + BCs). Fase 5 piloto: aggregates Encounter/Condition + VO ChiefComplaintReason wired desde Application. Pendiente: extender aggregates a más módulos y cerrar el plan.

## Relacionado

- [clinical-modulos-capacidad.md](./clinical-modulos-capacidad.md)
- [shared-top-level-infrastructure.md](./shared-top-level-infrastructure.md)
- [ddd-bounded-contexts-capas-y-metadata.md](./ddd-bounded-contexts-capas-y-metadata.md)
- `Domain/README.md`, `Clinical/README.md`
