# Gramática de carpetas y sufijos en Domain/

**Estado:** aceptado.  
**Norte (contenido de capas + un eje por nivel):** [ddd-norte-modelo-rico.md](./ddd-norte-modelo-rico.md).

## Contexto

Referencia: Vernon / eShop / Clean Architecture (`application` · `domain` · `infrastructure`).  
Empaquetar **un solo eje por nivel**: no hermanos `UseCase/` + `Checkpoint/`.

## Decisión

### Clinical — módulo

```text
Domain/Clinical/<Modulo>/
  Application/
    <Capacidad>/                # eje: lenguaje ubicuo
      UseCase/                  # rol CA (anidado)
      Presentation/             # rol CA (anidado), si aplica
      …                         # services/resolvers de esa capacidad
    Flows/ | Agents/ | Authorization/   # plugins producto (nombre propio)
  Domain/
    Model/ | Catalog/ | Policy/ | Port/ | RowContract/ | …
  Infrastructure/
    External/ | Persistence/ | <Adapter>/…
```

**Prohibido L1 del módulo:** `Service/`, `Support/`, `Legacy/`, `Workflow/`, `Checkpoint/` (como hermano de Application), PHP suelto en raíz.

**Prohibido bajo Application:** `UseCase/` o `Presentation/` como **hermanos** de carpetas de capacidad. Van **dentro** de la capacidad dueña.

### Eje por nivel (resumen)

| Nivel | Eje |
|-------|-----|
| `Application/*` | Capacidad / lenguaje |
| `Application/<Capacidad>/*` | Rol CA (`UseCase/`, `Presentation/`) o clases de esa capacidad |
| `Domain/*` | Building block (`Model/`, `Catalog/`, …) |
| `Infrastructure/*` | Adapter / tech |

### Referencia — Capture

```text
Capture/Application/
  Checkpoint/
    UseCase/            # CreateOrUpload, Transcribe, Save, …
    Presentation/       # ClinicalCapturePresenter
    ClinicalCaptureCheckpoint.php
  Extraction/
    UseCase/            # AnalyzeClinicalNote, AnalyzeClinicalCaptureDraft
    ConsultaProcesamientoService.php, post-proceso, …
  Definition/           # categorías, bootstrap, contexto, sanitizer
  RowContract/          # wiring + ResolutionApplier
```

### Sufijos → carpeta

| Sufijo | Carpeta |
|--------|---------|
| Interactor | `Application/<Capacidad>/UseCase/` |
| `*Presenter` / `*PresentationService` | `Application/<Capacidad>/Presentation/` (o Presentation del módulo dueño) |
| `*Service` de una capacidad | `Application/<Capacidad>/` |
| `*Access` | `Application/Authorization/` |
| `*Catalog`, policies | `Domain/Catalog/`, `Domain/Policy/`, … |
| `*RowContract` | `Domain/RowContract/` (+ `Application/RowContract/` wiring) |
| Aggregate | `Domain/Model/` |
| ACL | `Infrastructure/External/…` |
| AR Yii | `common/models/<BC>/` |

### Forma implantada

| Ámbito | Resultado |
|--------|-----------|
| Capture | Capacidades Application + UseCase/Presentation anidados; sin facade legacy |
| Resto Clinical | Tríada; migrar al mismo eje al tocar el módulo |

## Relacionado

- [ddd-norte-modelo-rico.md](./ddd-norte-modelo-rico.md)
- [clinical-modulos-capacidad.md](./clinical-modulos-capacidad.md)
- `Capture/README.md`, `Domain/README.md`, `Clinical/README.md`
- Test: `BoundedContextLayerShapeTest`
