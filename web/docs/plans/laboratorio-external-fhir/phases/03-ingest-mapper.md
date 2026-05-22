# Fase 3 — Ingesta

## Objetivo

Mapper FHIR → BD con idempotencia `source_system` + `external_id`.

## Entregables

- [x] `FhirDiagnosticReportMapper`
- [x] `LaboratoryIngestService::syncForPersona`
- [x] `LaboratoryEncounterLinkService` (enlace encounter)

## DoD

Re-sync no duplica filas; actualiza status/conclusión si el LIS cambia.
