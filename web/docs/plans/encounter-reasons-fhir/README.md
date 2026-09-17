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
| Chat pre-consulta | `interaccion_motivos_consulta` (`AppointmentReasonMessage`; no es el motivo clínico) |
| Sugerencias IA (hipótesis/prácticas) | `encounter.ia_clinical_suggestions` |
| Marca de batch IA | `encounter.motivos_ia_processed_at` (proceso; no es el motivo) |

Intake estructurado (`motivos_intake_json`) eliminado: el camino vivo es chat → Condition CC. La guía del chat (`AppointmentReasonChatGuideCatalogService`) sigue como metadata, sin persistir respuestas en encounter.

## Estado

Hecho: dominio, captura, batch, export FHIR, migración `m260917_*`, tipología `EncounterReason`, ADR; rename insights + drop intake (`m260917_150000_*`).

Pendiente de cierre: borrar esta carpeta de plan cuando se archive.

`reason_text` de triaje de guardia es otro concepto.
