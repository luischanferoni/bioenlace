# CareCohort — packs por similitud de perfil

Generación en cola de packs reutilizables (`assistance_questions`, `followup_program`, `education_bundle`) keyed por `cohort_key` (SHA-256 de perfil estable).

## Activación

`frontend/config/params.php` → `care_cohort.enabled = true`

Vertex batch opcional: `care_cohort.vertex_batch` (bucket GCS + cuenta de servicio con `cloud-platform`).

## Cron

```bash
php yii care-pack/run-jobs
php yii care-pack/poll-vertex   # si vertex_batch.enabled
```

## Hooks

- `EncounterLifecycleService::ensureFromTurno` → asistencia pre-consulta
- `EncounterLifecycleService::finalize` → seguimiento + educación

## API paciente (Fase 2)

- `GET|POST /api/v1/care-packs/assistance?encounter_id=` o `turno_id=`

## Plan

`web/docs/plans/cohortes-asistencia-batch/`
