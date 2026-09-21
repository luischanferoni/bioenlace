# Capture (`Domain/Clinical/Capture`)

Capacidad: **intake de documentación clínica** — texto/audio → normalización → extracción → issues resolubles → checkpoint de captura.

Norte: [ddd-norte-modelo-rico.md](../../../../../docs/decisions/ddd-norte-modelo-rico.md).

## Límites

| Sí (Capture) | No |
|--------------|-----|
| Pipeline `/captura/*`, STT, post-proceso, issues | Guardar nota FHIR → `Encounter/Application/Documentation/` |

## Forma

```text
Application/
  UseCase/          etapas HTTP (Analyze*, Save*, Transcribe*, …)
  RowContract/      wiring + registry compuesto + ResolutionApplier
  Pipeline/         helpers compartidos (no entrypoint)
  Text/             post-proceso / knobs / validator de términos
  Workflow/         resolvers / sanitizer de definición
  *Service.php      orquestadores Application (raíz)

Domain/
  Catalog/          *Catalog (actor, workflow, medicación, encounter class)
  Model/            aggregate, VO, stages, issue factory
  RowContract/      *RowContract + ExtractedRowFields (integridad por tipología)
  Policy/           CompletenessValidator, ExtractionPostProcessPolicy
  Port/             Repository, RowContractRegistry, DerivacionRowSupport, STT, Terminology

Infrastructure/     adapters Yii / STT / terminology / logging
```

## Oleadas

| Oleada | Estado |
|--------|--------|
| 1–3b | hechas (Domain rico, Documentation en Encounter, pipeline vía aggregate) |
| 4 | hecha — use cases por etapa; controller apunta a ellos; Support interno |
| 4b | hecha — cuerpos de etapa en cada use case; Support solo helpers |
| 5 | hecha — Domain Policy sin Platform; knobs vía `EncounterCapturePostProcessKnobs` |
| 5b | hecha — VO `ClinicalCaptureResolution` en Applier |
| 6 | hecha — completitud/resoluciones vía port `ClinicalCaptureRowContractRegistry` (adapter Yii/`*Input`) |
| 6b | hecha — Domain Policy sin default Infra; factory Application; catálogos en `Domain/Catalog` |
| 7 | hecha — `templateForOffering(itemName, serviceName, class)`; Domain Catalog sin AR |
| 7b | parcial — piloto `EncounterReasonRowContract` + registry compuesto Domain→Yii |
| 8 | parcial — Domain contracts: Practica, Regimen, Oftalmología estudio, Odontología ítem |
| 9 | hecha — Indicación, Medicación, Balance hídrico en Domain (+ `MedicacionCaptureCatalog`) |
| 10 | hecha — Derivación vía `DerivacionRowContract` + port `DerivacionRowSupportPort` |
| 11 | hecha — reorganización por sufijo/carpeta (`UseCase/`, `RowContract/`, `EncounterClassCatalog`) |

## Deuda restante

- Tipologías residuales sin contrato Domain si aparecen (p. ej. DiagnosticoConsulta / signos vitales).
- Adapter de derivación aún habla con AR `Servicio` (esperado en Infrastructure).

## Referencias

- Persistencia: `Encounter/Application/Documentation/EncounterDocumentationService`
- Producto: [captura-clinica.md](../../../../../docs/producto/captura-clinica.md)
