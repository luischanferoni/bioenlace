# Design — motivos como Condition (CC)

## FHIR

- `Encounter.reasonReference` → Condition(s) del acto con rol chief complaint.
- `Encounter.reasonCode` en bundle: code/display derivados de esas Conditions.
- `Encounter.note` ≠ motivo.

## Discriminador

Constante de dominio `ConditionDiagnosisRole::CHIEF_COMPLAINT = 'CC'`.

Listados de “diagnósticos” deben excluir `CC`. Listados de “motivos” solo `CC`.

## API de dominio

`EncounterReasonService`:

- `list(Encounter): Condition[]`
- `displayText(Encounter): string` — join de displays para prompts/UI de una línea (no es columna).
- `replaceFromTexts(Encounter, list<string>|list<row>): void` — soft-delete motivos previos del encounter y crea nuevos.
- `hasReasons(Encounter): bool`

## Captura

Categoría `EncounterReason` → `EncounterReasonInput` (texto requerido; código opcional) → `replaceFromTexts` / filas tipadas.

## Batch paciente

`AppointmentReasonBatchService` genera el resumen y llama `replaceFromTexts` (una Condition con el resumen, o varias si el modelo lo pide). Marca `motivos_ia_processed_at`. Insights siguen en JSON de proceso.

## Schema

```sql
ALTER TABLE clinical_condition MODIFY code varchar(32) NULL;
-- backfill
INSERT INTO clinical_condition (...) SELECT ... FROM encounter WHERE reason_text IS NOT NULL AND TRIM(reason_text) <> '';
ALTER TABLE encounter DROP COLUMN reason_text;
```

Vista `view_encounter_diagnostico`: filtrar `diagnosis_role <> 'CC'` (o IS NULL / principal|secondary) para no mezclar motivos en diagnósticos previos.
