# Norte DDD: modelo rico (Domain primero)

**Estado:** aceptado (norte de diseño).  
**Piloto:** `Clinical/Capture`.  
**Relacionados:** [domain-folder-grammar.md](./domain-folder-grammar.md), [clinical-modulos-capacidad.md](./clinical-modulos-capacidad.md), [ddd-bounded-contexts-capas-y-metadata.md](./ddd-bounded-contexts-capas-y-metadata.md).

Si hay conflicto entre packaging y responsabilidad de capa, **gana este norte**.

---

## Regla de oro — cero ambigüedad

**Un eje por nivel. Bajo `Application/` el eje es técnico (rol Clean Architecture). El nombre de producto/dominio va en las clases (y en `Domain/`), no en carpetas hermanas de `UseCase/`.**

| Nivel | Eje | Ejemplo |
|-------|-----|---------|
| `Domain/<BC>/` | Bounded context | `Clinical/` |
| `Clinical/<Modulo>/` | Capacidad de producto | `Capture/`, `Encounter/` |
| `<Modulo>/` L1 | Capa DDD/CA | `Application/`, `Domain/`, `Infrastructure/` |
| `Application/*` | **Rol CA (técnico)** | `UseCase/`, `Presentation/`, `Service/`, `Authorization/`, `Flows/`, `Agents/` |
| `Domain/*` | Building block | `Model/`, `Catalog/`, `Policy/`, `Port/`, `RowContract/` |
| `Infrastructure/*` | Adapter / tech | `Persistence/`, `SpeechToText/`, `Logging/` |
| **Clases / archivos** | Lenguaje de dominio | `SaveCapture`, `CaptureDraftService`, `MedicacionRowContract` |

### Prohibido (causa el híbrido que ya rechazamos)

- Carpetas de **capacidad/dominio** bajo `Application/` al lado de roles CA:  
  `Application/Checkpoint/`, `Extraction/`, `Definition/`, `RowContract/`, `Support/`, `Helpers/`, `Pipeline/`…
- Mezclar en el mismo nivel nombres de dominio y nombres técnicos.
- Facades legacy que re-envuelven todos los use cases.

### Correcto

```text
Clinical/Capture/
  Application/
    UseCase/SaveCapture.php          ← rol técnico; dominio en el nombre de clase
    Presentation/CapturePresenter.php
    Service/CaptureDraftService.php    ← colaborador Application; dominio en el nombre
    Service/CaptureExtractionService.php
  Domain/
    Model/ClinicalCapture.php
    RowContract/MedicacionRowContract.php    ← dominio/building block aquí, no bajo Application
  Infrastructure/
    Persistence/…
```

---

## Capas (contenido)

| Capa | Qué va | Qué no va |
|------|--------|-----------|
| **Domain** | Aggregates, VO, policies, catálogos, ports | Yii, HTTP, filesystem, AR |
| **Application** | Use cases, presenters, services de aplicación finos | Invariantes / léxico clínico de Domain |
| **Infrastructure** | Adapters | Semántica clínica |

## Application fino

Una intención = verb phrase en `Application/UseCase/` (`SaveCapture`, …).  
Colaboradores con **sufijo del catálogo transversal** (`*Service`, `*Resolver`, `*Applier`, `*Presenter`) — **nunca** metáforas locales (`Checkpoint`, `Pipeline`, `Normalizer`, `Sanitizer`, `PostProcessor`, `Overrides`).

## Naming de clases

Patrón: `[<Contexto>]<Concepto><SufijoTécnico>` — ver [domain-folder-grammar.md](./domain-folder-grammar.md).

Los sufijos son **técnicos y transversales** a toda la arquitectura. Si no está en el catálogo, no es sufijo de arquitectura.

- Aggregate: `ClinicalCapture` (+ Id/Stage/Repository anclados).
- Application: `CaptureDraftService`, `ExtractionPostProcessService`, …
- Domain rules: `*Policy` (p. ej. `ExtractedTermPolicy`), no `*Validator` en Application.
- AR/tabla `EncounterCapture` solo en Infra/`common/models`.

## Ports / adapters

Ports en `Domain/Port/`; adapters en `Infrastructure/`.

## Plugins Bioenlace

`Flows/`, `Agents/`, `Authorization/` son roles/adapters de producto en `Application/` (nombres técnicos de plugin, no capacidades clínicas inventadas).  
`Assistant/`, `Home/` en raíz del BC según gramática.

## Capture

Intake clínico; no fachada del HIS. Persistencia nota → Encounter Documentation.  
Forma: [Capture/README.md](../../common/components/Domain/Clinical/Capture/README.md).

## Checklist

1. ¿Esta carpeta bajo `Application/` es un **rol CA** (`UseCase`, `Presentation`, `Service`, …)? Si es nombre de negocio (`Checkpoint`, `Extraction`) → **mover**: carpeta fuera; dominio al nombre de clase o a `Domain/`.
2. ¿El sufijo está en el catálogo CA/DDD? Si no (`Checkpoint`, `Pipeline`, `Helper`) → renombrar.
3. ¿Regla sin I/O? → Domain.
4. ¿Orquestación de intención? → `UseCase/`.
5. ¿I/O? → Infrastructure.

## Alternativas descartadas

- Package-by-feature bajo `Application/` (`Checkpoint/`, `Extraction/`, …).
- Híbrido hermanos rol CA + capacidad.
- `Support/` / facades legacy.
- Sufijos metáfora no CA (`*Checkpoint`, `*AnalysisService` opaco).
