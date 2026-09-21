# Capture (`Domain/Clinical/Capture`)

Capacidad: **intake de documentación clínica** — texto/audio → normalización → extracción → issues resolubles → checkpoint de captura.

Norte: [ddd-norte-modelo-rico.md](../../../../../docs/decisions/ddd-norte-modelo-rico.md), [domain-folder-grammar.md](../../../../../docs/decisions/domain-folder-grammar.md).

## Límites

| Sí (Capture) | No |
|--------------|-----|
| Flujo `/captura/*`, STT, post-proceso, issues | Guardar nota FHIR → `Encounter/Application/Documentation/` |

## Eje de carpetas en `Application/`

**Eje único: lenguaje ubicuo / capacidad** (preocupación de negocio del intake), no cajones técnicos (`Support/`, `Helpers/`, `Shared/`).

Excepciones de gramática CA del repo ([domain-folder-grammar.md](../../../../../docs/decisions/domain-folder-grammar.md)):

| Carpeta | Rol |
|---------|-----|
| `UseCase/` | Entrypoints / interactors (una intención = una clase) |
| `Presentation/` | `*Presenter` — forma API del checkpoint |

El resto son **capacidades** del language de captura:

| Carpeta | Capacidad |
|---------|-----------|
| `Checkpoint/` | Lookup, persistencia del aggregate, audio del checkpoint |
| `Extraction/` | Texto clínico, post-proceso IA, análisis (`ConsultaProcesamientoService`, …) |
| `RowContract/` | Wiring Application ↔ `Domain/RowContract` + resoluciones |
| `Definition/` | EncounterDefinition: categorías, bootstrap, contexto operativo, sanitizer |

## Forma

```text
Application/
  UseCase/          etapas HTTP (Analyze*, Save*, Transcribe*, …)
  Checkpoint/       ClinicalCaptureCheckpoint (lookup / persist / audio)
  Presentation/     ClinicalCapturePresenter (ok / fail / toApiArray)
  Extraction/       texto + post-proceso + análisis IA
  RowContract/      factory, registry compuesto, ResolutionApplier
  Definition/       definición de encounter / categorías / bootstrap

Domain/
  Catalog/          *Catalog
  Model/            aggregate, VO, stages, issue factory
  RowContract/      *RowContract + ExtractedRowFields
  Policy/           CompletenessValidator, ExtractionPostProcessPolicy
  Port/             Repository, RowContractRegistry, DerivacionRowSupport, STT, Terminology

Infrastructure/     adapters Yii / STT / terminology / logging
```

## Oleadas

| Oleada | Estado |
|--------|--------|
| 1–3b | hechas (Domain rico, Documentation en Encounter, pipeline vía aggregate) |
| 4–4b | hechas — use cases por etapa; Support/helpers internos |
| 5–5b | hechas — Domain Policy sin Platform; VO Resolution |
| 6–6b | hechas — port RowContractRegistry; catálogos Domain |
| 7–10 | tipologías Domain + Derivacion port (parcial en 7b–8) |
| 11 | hecha — carpeta/sufijo (`UseCase/`, `RowContract/`, `EncounterClassCatalog`) |
| 12 | hecha — eje Application por capacidad (`Checkpoint/`, `Extraction/`, `Definition/`, `Presentation/`) |

## Deuda restante

- Tipologías residuales sin contrato Domain si aparecen (p. ej. DiagnosticoConsulta / signos vitales).
- Adapter de derivación aún habla con AR `Servicio` (esperado en Infrastructure).

## Referencias

- Persistencia: `Encounter/Application/Documentation/EncounterDocumentationService`
- Producto: [captura-clinica.md](../../../../../docs/producto/captura-clinica.md)
