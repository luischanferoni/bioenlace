# Motivos de consulta como Condition FHIR (chief complaint)

## Contexto

`encounter.reason_text` mezclaba el motivo clínico con un campo del Encounter. En FHIR el motivo es `reasonCode` / `reasonReference` (típicamente Condition).

## Decisión

- Persistencia canónica: filas en `clinical_condition` con `diagnosis_role = CC` (DiagnosisRole *Chief complaint*).
- API de dominio: `EncounterReasonService`.
- `encounter.note` = narrativa del acto (no motivo).
- Columna `encounter.reason_text` eliminada (migración `m260917_140000_encounter_reasons_as_condition_cc`).
- Chat pre-consulta (`interaccion_motivos_consulta`) y JSON de intake/insights IA siguen siendo artefactos de proceso, no el recurso motivo.

## Consecuencias

- Diagnósticos y motivos se listan por separado (rol).
- Export FHIR emite `reasonCode`/`reasonReference` y Conditions CC en el Bundle.
- Sin capa de compatibilidad: lectores/escritores usan solo el servicio de motivos.
