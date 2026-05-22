# Fase 1 — BD y modelos

## Objetivo

Tabla `diagnostic_report` y columnas de trazabilidad en `observation`.

## Entregables

- [x] Migración `m260523_100001_laboratory_diagnostic_report`
- [x] AR `Clinical\DiagnosticReport`
- [x] `observation`: `source_system`, `external_id`, `diagnostic_report_id`; `encounter_id` nullable

## DoD

Migración aplica en dev; modelos con `ClinicalRecordTrait`.
