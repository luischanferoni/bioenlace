# Capture (`Domain/Clinical/Capture`)

Capacidad: **intake de documentación clínica** — texto/audio → normalización → extracción → issues resolubles → checkpoint de captura.

Norte: [ddd-norte-modelo-rico.md](../../../../../docs/decisions/ddd-norte-modelo-rico.md).

## Límites

| Sí (Capture) | No |
|--------------|-----|
| Pipeline `/captura/*`, STT, post-proceso, issues | Guardar nota FHIR → `Encounter/Application/Documentation/` |

## Forma

```text
Application/ CompositeClinicalCaptureRowContractRegistry
Domain/Policy/ *RowContract (casi todas las tipologías de captura)
Domain/Catalog/ MedicacionCaptureCatalog, Encounter*Catalog
Infrastructure/ YiiModel…  # fallback: Derivación (+ tipologías sin contrato)
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

## Deuda restante

- Derivación (`DerivacionInput`) aún vía Yii/`PedidoAtencion` (port de resolución pendiente).
- Tipologías residuales sin contrato Domain si aparecen (p. ej. DiagnosticoConsulta / signos vitales).

## Referencias

- Persistencia: `Encounter/Application/Documentation/EncounterDocumentationService`
- Producto: [captura-clinica.md](../../../../../docs/producto/captura-clinica.md)
