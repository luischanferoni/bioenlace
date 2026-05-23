# Design — Laboratorio

## Por qué integrador y no LIS propio

El procesamiento analítico vive en el LIS del efector o red; Bioenlace consume FHIR y enlaza al paciente/encounter.

**Alternativa descartada:** módulo LIS con import CSV dengue y tablas `laboratorio*` — retirado en migración `m260523_100003`.

## Conectores

- Interfaz `FhirLabResultsConnector` en `Integrations/Laboratory/`.
- `LabConnectorRegistry` lee `params['laboratoryConnectors']` (`default` + `connectors.<key>`).
- Nuevo proveedor = nueva clase + entrada en config (sin tocar ingesta).

## Persistencia

| Tabla | Rol |
|-------|-----|
| `diagnostic_report` | Informe (panel), `payload_json` FHIR, idempotencia `source_system` + `external_id` |
| `observation` | Analitos (`category=exam`), opcional `diagnostic_report_id` |

**Alternativa descartada:** solo JSON sin columnas — dificulta listados y apps móviles.

## Encounter

Se intenta `Encounter/{id}` del recurso FHIR; si no, encounter del mismo paciente en la fecha de `issued`. Puede quedar `encounter_id` null.

## Terminología

Códigos tal como vienen en FHIR (LOINC/SNOMED). Sin tabla `laboratorio_nbu_snomed`.

## Canal paciente

- **Consultar**: lectura local; `GET mis-resultados-como-paciente` (ui_json) — [flow](./flows/consultar-resultados-paciente.md).
- **Ingesta**: consola/cron (`laboratory-sync/lote`) — [ingesta-cron](./flows/ingesta-cron.md). No expuesta al paciente en API ni asistente.
- Asistente: intent `laboratorio.ver-resultados-como-paciente` (ver [intents](./flows/intents-laboratorio-paciente.md)).
