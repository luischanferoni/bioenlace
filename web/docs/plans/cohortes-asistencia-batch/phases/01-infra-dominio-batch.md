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
- [ ] Documentar contextos en `catalogo-usos-ia.md` (al cerrar fase)

## Cron sugerido

```bash
# Cola sync + submit Vertex cada 5 min
php yii care-pack/run-jobs
# Poll jobs Vertex cada 15 min
php yii care-pack/poll-vertex
```
