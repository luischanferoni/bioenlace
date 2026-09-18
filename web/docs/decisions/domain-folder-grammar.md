# Gramática de carpetas y sufijos en Domain/

**Estado:** aceptado e implantado (Clinical + BCs Content/Geo/Terminology/Organization/Person/Scheduling).

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
    Model/                      # Aggregates (reglas puras; sin Yii/DB)
    …                           # *Catalog, enums, policies tipadas
  Infrastructure/
    External/<Sistema>/…        # Connector|Contract|Dto|Exception|Mapper|Registry
    Persistence/
      README.md                 # AR Yii → common/models/Clinical/ (no duplicar)
```

Plugins del BC **solo** en raíz Clinical: `Assistant/`, `Home/`, `DataAccess/`. Misma tríada `Application/` · `Domain/` · `Infrastructure/`; además `Home/Sections/` para `*SectionProvider` (no es L1 de módulo de capacidad).

Controllers API y `views/json`: `frontend/modules/api/v1/…` (borde Presentation del deploy; **fuera** de `components/Domain`).

**Prohibido en L1 del módulo:** `Service/`, `Dto/`, `Presentation/`, `Legacy/`, `Support/`, `Mapper/`, `Batch/`, `PatientSummary/`, `AiContext/`, `Workflow/`, PHP suelto en raíz del módulo o del BC; `Clinical/Infrastructure/` en raíz del BC; `Application/Legacy/`.

Todo L1 ad-hoc se reubica bajo `Application/`, `Domain/` o `Infrastructure/`.

### BC chico / mediano

Misma tríada `Application/` · `Domain/` · `Infrastructure/` en la raíz del BC (sin módulo), más plugins `Assistant/` | `Home/` | `DataAccess/` si aplican. Áreas de lenguaje (`Ventanilla`, `Quirofano`, `Representation`, …) con las mismas tres capas internas.

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

### Aggregates (`Domain/Model`)

- Sin I/O ni Yii: Application reconstituye desde AR, aplica mutaciones y persiste.
- Piloto vivo en **Encounter**: `Encounter` (`open`/`finish`/`cancel`), `Condition` (`transitionTo`), VO `ChiefComplaintReason`.
- Extender reglas a más módulos es trabajo continuo (no requiere carpeta de plan): mover invariantes desde Application Services al aggregate correspondiente.

### Forma implantada (resumen)

| Ámbito | Resultado |
|--------|-----------|
| Clinical (módulos) | Tríada + `Application/Agents/`; sin `Service/` L1 |
| Content, Geo, Terminology | Tríada; Terminology ACL Snowstorm bajo External |
| Organization, Person, Scheduling | Tríada; Person Representation/Ventanilla y Scheduling Quirofano como áreas |
| Captura IA legacy | `ConsultaProcesamientoService` / `ConsultaLogger` en `Capture/Application/` (no `Legacy/`) |

## Relacionado

- [clinical-modulos-capacidad.md](./clinical-modulos-capacidad.md)
- [shared-top-level-infrastructure.md](./shared-top-level-infrastructure.md)
- [ddd-bounded-contexts-capas-y-metadata.md](./ddd-bounded-contexts-capas-y-metadata.md)
- `Domain/README.md`, `Clinical/README.md`
- Test de forma: `BoundedContextLayerShapeTest`
