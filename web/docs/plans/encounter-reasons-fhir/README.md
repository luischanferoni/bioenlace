# Encounter reasons (motivos) → FHIR Condition

## Objetivo

Motivo de consulta es un **recurso clínico hijo** del Encounter (`Condition` con rol chief complaint), no una columna del encounter. Sin compatibilidad legacy: se elimina `encounter.reason_text`.

## Modelo canónico

| Concepto | Persistencia |
|----------|----------------|
| Motivo(s) del acto | `clinical_condition` con `diagnosis_role = CC` (FHIR DiagnosisRole *Chief complaint*) |
| Diagnóstico | `clinical_condition` con `principal` / `secondary` (sin cambio) |
| Nota narrativa | `encounter.note` |
| Chat pre-consulta | `interaccion_motivos_consulta` (mensajes; no es el motivo clínico) |
| Artefactos de proceso IA | `motivos_ia_processed_at`, `motivos_ia_insights_json`, `motivos_intake_json` (siguen en encounter; no son el motivo) |

Código libre / aún no codificado: `code` nullable; `display` (y opcionalmente `note`) lleva el texto. Sistema por defecto SNOMED cuando hay concept id.

## Fuera de alcance de este plan

- Renombrar la categoría de captura IA `ConsultaMotivos` en prompts YAML (requiere edición humana). El id de categoría se mantiene; la persistencia deja de usar `reason_text`.
- Fusionar chat pre-consulta en Condition.

## Fases

1. Dominio: `ConditionDiagnosisRole`, `EncounterReasonService`, relaciones Encounter, `MotivoInput` + tipología.
2. Escritura: captura (`persistMotivos`), batch pre-consulta, seeds.
3. Lectura: vistas staff, timeline, journey, AI context, export FHIR, async, etc. — sin `reason_text`.
4. Migración Yii: backfill `reason_text` → Condition CC; `DROP COLUMN reason_text`; `code` nullable en `clinical_condition`.
5. Grep cero `reason_text` en PHP/JS (salvo docs de plan / migraciones históricas).
6. Al cerrar: ADR corto en `docs/decisions/` y borrar esta carpeta de plan.

## Estado

Implementación en curso / núcleo listo: dominio, captura, batch, export FHIR, migración `m260917_140000_*`, ADR en `docs/decisions/encounter-reasons-condition-cc.md`.

`reason_text` de **triaje de guardia** (`guardia_triage`) es otro concepto; no forma parte de este plan.
