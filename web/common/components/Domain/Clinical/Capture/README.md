# Capture (`Domain/Clinical/Capture`)

Capacidad: **intake de documentación clínica** — texto/audio → normalización → extracción → issues resolubles → checkpoint de captura.

**Norte DDD / CA:** [ddd-norte-modelo-rico.md](../../../../../docs/decisions/ddd-norte-modelo-rico.md) (capas + eje de subcarpetas §4).  
**Gramática de carpetas:** [domain-folder-grammar.md](../../../../../docs/decisions/domain-folder-grammar.md).  
**Módulo en Clinical:** [clinical-modulos-capacidad.md](../../../../../docs/decisions/clinical-modulos-capacidad.md).

## Límites

| Sí (Capture) | No |
|--------------|-----|
| Flujo `/captura/*`, STT, post-proceso, issues | Guardar nota FHIR → `Encounter/Application/Documentation/` |

## Eje de carpetas (no desviarse)

En **todo** el módulo el eje es el mismo:

> **Capacidad / lenguaje ubicuo** — no cajones técnicos (`Support/`, `Helpers/`, `Shared/`, `Pipeline/` genérico, facades legacy).

| Capa | Cómo se aplica aquí |
|------|---------------------|
| **Domain** | `Model/`, `Catalog/`, `Policy/`, `RowContract/`, `Port/` |
| **Application** | Capacidades abajo + roles CA `UseCase/` y `Presentation/` |
| **Infrastructure** | Adapters temáticos (`Persistence/`, `SpeechToText/`, `Terminology/`, `Logging/`, …) |

Roles CA bajo `Application/` (excepciones estables, no “helpers”):

| Carpeta | Rol |
|---------|-----|
| `UseCase/` | Interactors: una intención = una clase (entrypoints del controller API) |
| `Presentation/` | `ClinicalCapturePresenter` — forma API (ok / fail / toApiArray) |

Capacidades Application (lenguaje del intake):

| Carpeta | Capacidad |
|---------|-----------|
| `Checkpoint/` | Lookup, persistencia del aggregate, audio del checkpoint |
| `Extraction/` | Texto clínico, post-proceso IA, análisis |
| `RowContract/` | Wiring ↔ `Domain/RowContract` + resoluciones |
| `Definition/` | EncounterDefinition: categorías, bootstrap, contexto operativo, sanitizer |

Entrypoint HTTP: `frontend/.../EncounterController` → `UseCase/*` (no hay facade intermedia).

## Forma

```text
Application/
  UseCase/
  Checkpoint/       ClinicalCaptureCheckpoint
  Presentation/     ClinicalCapturePresenter
  Extraction/
  RowContract/
  Definition/

Domain/
  Catalog/          *Catalog (incl. EncounterClassCatalog)
  Model/            ClinicalCapture, stages, VO, issue factory
  RowContract/      *RowContract + ExtractedRowFields
  Policy/           CompletenessValidator, ExtractionPostProcessPolicy
  Port/

Infrastructure/     Persistence, SpeechToText, Terminology, Logging, PedidoAtencion adapter
```

## Oleadas (resumen)

| Oleada | Estado |
|--------|--------|
| 1–10 | Domain rico, ports, tipologías RowContract, use cases por etapa |
| 11–12 | Empaquetado Application por capacidad + Presenter; sin facade legacy |

## Deuda restante

- Tipologías residuales sin contrato Domain si aparecen (p. ej. DiagnosticoConsulta / signos vitales).
- Adapter de derivación aún habla con AR `Servicio` (esperado en Infrastructure).

## Referencias

- Persistencia nota: `Encounter/Application/Documentation/EncounterDocumentationService`
- Producto: [captura-clinica.md](../../../../../docs/producto/captura-clinica.md)
