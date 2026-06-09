# Fase 1 — Infra, dominio y cola batch

**Estado:** en curso

## Checklist

- [x] Migración `care_cohort_pack`, `care_pack_job`, `care_encounter_pack`
- [x] `CohortKeyBuilder`, `CarePackType`
- [x] `CarePackGenerationService` (sync / `IAManager`)
- [x] `CarePackJobProcessor` + `CarePackController`
- [x] `VertexBatchPredictionClient` + `GcsSimpleUploader` (si bucket configurado)
- [x] Hooks `EncounterLifecycleService`
- [x] Params `care_cohort`
- [x] Test unitario `CohortKeyBuilderTest`
- [x] Documentar contextos en `catalogo-usos-ia.md` (pendiente al cerrar programa)

## Cron sugerido

Ver **`web/docs/producto/asistencia-cohortes.md`** (frecuencias, crontab, relación con otros jobs).

```bash
# Cada 5 min — obligatorio
php yii care-pack/run-jobs
# Cada 15 min — refuerzo poll Vertex (opcional)
php yii care-pack/poll-vertex
```
