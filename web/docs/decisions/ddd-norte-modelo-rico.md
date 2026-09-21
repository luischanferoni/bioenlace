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
| **Clases / archivos** | Lenguaje de dominio | `SaveClinicalCapture`, `ClinicalCaptureCheckpoint`, `MedicacionRowContract` |

### Prohibido (causa el híbrido que ya rechazamos)

- Carpetas de **capacidad/dominio** bajo `Application/` al lado de roles CA:  
  `Application/Checkpoint/`, `Extraction/`, `Definition/`, `RowContract/`, `Support/`, `Helpers/`, `Pipeline/`…
- Mezclar en el mismo nivel nombres de dominio y nombres técnicos.
- Facades legacy que re-envuelven todos los use cases.

### Correcto

```text
Clinical/Capture/
  Application/
    UseCase/SaveClinicalCapture.php          ← rol técnico; dominio en el nombre de clase
    Presentation/ClinicalCapturePresenter.php
    Service/ClinicalCaptureCheckpoint.php    ← colaborador Application; dominio en el nombre
    Service/ClinicalCaptureAnalysisService.php
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

Una intención = una clase en `Application/UseCase/`.  
Colaboradores (`*Checkpoint`, `*Service`, resolvers) en `Application/Service/` — rol CA técnico; dominio en el nombre de clase — **nunca** bajo carpetas con nombre de capacidad de producto.

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
2. ¿Regla sin I/O? → Domain.
3. ¿Orquestación de intención? → `UseCase/`.
4. ¿I/O? → Infrastructure.

## Alternativas descartadas

- Package-by-feature bajo `Application/` (`Checkpoint/`, `Extraction/`, …).
- Híbrido hermanos rol CA + capacidad.
- `Support/` / facades legacy.
