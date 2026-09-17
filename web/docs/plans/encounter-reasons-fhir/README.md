# Encounter reasons (motivos) → FHIR Condition

## Objetivo

Motivo de consulta es un **recurso clínico hijo** del Encounter (`Condition` con rol chief complaint), no una columna del encounter. Sin compatibilidad legacy: se elimina `encounter.reason_text`.

## Modelo canónico

| Concepto | Persistencia |
|----------|----------------|
| Motivo(s) del acto | `clinical_condition` con `diagnosis_role = CC` |
| Tipología / categoría captura | `EncounterReason` (+ `EncounterReasonInput`) |
| Diagnóstico | `clinical_condition` con `principal` / `secondary` |
| Nota narrativa | `encounter.note` |
| Chat pre-consulta | `interaccion_motivos_consulta` (`ConsultaMotivosMessage`; no es el motivo clínico) |
| Artefactos de proceso IA | `motivos_ia_*` / `motivos_intake_json` en encounter |

## Estado

Hecho: dominio, captura, batch, export FHIR, migración `m260917_*`, tipología `EncounterReason`, ADR.

Pendiente de cierre: borrar esta carpeta de plan cuando se archive.

`reason_text` de triaje de guardia es otro concepto.
