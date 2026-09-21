# Capture (`Domain/Clinical/Capture`)

Capacidad: **intake de documentación clínica** — texto/audio → normalización → extracción → issues resolubles → checkpoint de captura.

Norte de diseño: [ddd-norte-modelo-rico.md](../../../../../docs/decisions/ddd-norte-modelo-rico.md).

## Límites del módulo

| Sí (Capture) | No (otro dueño) |
|--------------|-----------------|
| STT clínico, texto, post-proceso de extracción | Ciclo de vida Encounter (`Encounter/`) |
| Completitud vs definition + issues | Cola/triage de guardia (`Emergency/`) |
| Pipeline por etapas `/captura/*` | Persistencia multi-módulo de “guardar nota” → `Encounter/Application/Documentation/` |
| Catálogos de actor / plantillas de workflow de definition | Care plan / órdenes (`CarePlan/`) |

**Nombre:** se mantiene `Capture`. El guardar nota vive en Encounter (`EncounterDocumentationService`).

## Forma objetivo

```text
Capture/
  Application/           # AnalyzeClinicalNote; EncounterCapturePipelineService (facade etapas)
  Domain/
    Model/               # ClinicalCapture + Stage + Id; IssueFactory
    Policy/              # completitud, post-proceso
    Port/                # SpeechToText, Terminology, ClinicalCaptureRepository
    *Catalog
  Infrastructure/
    Logging/
    Persistence/         # ActiveRecordClinicalCaptureRepository, analysis cache
    SpeechToText/
    Terminology/
```

## Oleadas

| Oleada | Estado | Contenido |
|--------|--------|-----------|
| 1 | hecha | Policies + IssueFactory → Domain; loggers/cache/STT/term → Infrastructure; ports; ADR norte |
| 2 | hecha | Aggregate `ClinicalCapture` + repo AR; pipeline `transcribir`/`analizar`/`descartar` vía aggregate; `AnalyzeClinicalNote` |
| 3 | hecha (1ª parte) | `EncounterDocumentationService` → `Encounter/Application/Documentation/`; API `analizar` → Capture, `guardar` → Encounter |
| 3b | pendiente | Pipeline `guardar`/`crearOSubir` 100% vía aggregate; retirar facades `analizar*` en DocumentationService |

## Deuda explícita

- Policies aún leen knobs Platform (`ClinicalTextIaMetadata`).
- Completitud resuelve `*Input` en models/.
- `ConsultaProcesamientoService` legacy; `EncounterCapturePipelineService` sigue orquestando HTTP.
- DocumentationService aún expone `analizar*` como delegación a Capture (compat).

## Referencias

- Producto: [captura-clinica.md](../../../../../docs/producto/captura-clinica.md)
- Integridad Yii vs YAML: [captura-clinica-contratos-yii-vs-yaml.md](../../../../../docs/decisions/captura-clinica-contratos-yii-vs-yaml.md)
- Persistencia documentación: `Encounter/Application/Documentation/EncounterDocumentationService`
