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

## Forma

```text
Capture/
  Application/           # AnalyzeClinicalNote; EncounterCapturePipelineService (facade etapas)
  Domain/
    Model/               # ClinicalCapture + Stage + Id; IssueFactory
    Policy/
    Port/                # SpeechToText, Terminology, ClinicalCaptureRepository
    *Catalog
  Infrastructure/
    Logging/ Persistence/ SpeechToText/ Terminology/
```

## Oleadas

| Oleada | Estado | Contenido |
|--------|--------|-----------|
| 1 | hecha | Policies/IssueFactory → Domain; adapters Infrastructure; ports; ADR norte |
| 2 | hecha | Aggregate + repo; pipeline etapas vía aggregate; `AnalyzeClinicalNote` |
| 3 | hecha | DocumentationService → Encounter; API analizar → Capture, guardar → Encounter |
| 3b | hecha | `crearOSubir` / `aplicarResoluciones` vía aggregate; sin `analizar*` en Documentation |

## Deuda restante

- Policies aún leen knobs Platform (`ClinicalTextIaMetadata`).
- Completitud resuelve `*Input` en models/.
- `ConsultaProcesamientoService` legacy; pipeline sigue siendo facade HTTP ancha.
- VO tipados para filas extraídas / resoluciones.

## Referencias

- Producto: [captura-clinica.md](../../../../../docs/producto/captura-clinica.md)
- Persistencia: `Encounter/Application/Documentation/EncounterDocumentationService`
