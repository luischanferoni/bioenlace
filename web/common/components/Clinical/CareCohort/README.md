# CareCohort — packs por similitud de perfil

Generación en cola de packs reutilizables (`assistance_questions`, `followup_program`, `education_bundle`) keyed por `cohort_key` (SHA-256 de perfil estable).

## Activación

`frontend/config/params.php` → `care_cohort.enabled = true`

### Vertex batch (producción)

`care_cohort.vertex_batch.enabled = true` + bucket GCS dedicado.

Guía completa: `web/docs/plans/cohortes-asistencia-batch/phases/05-vertex-batch-produccion.md`

Diagnóstico:

```bash
php yii care-pack/vertex-status
```

## Cron

```bash
php yii care-pack/run-jobs          # sync + submit + poll Vertex
php yii care-pack/poll-vertex       # refuerzo poll
php yii care-pack/vertex-status     # readiness + contadores cola
```

## Hooks

- `EncounterLifecycleService::ensureFromTurno` → asistencia pre-consulta
- `EncounterLifecycleService::finalize` → seguimiento + educación

## API paciente

- `GET|POST /api/v1/care-packs/assistance?encounter_id=` o `turno_id=`
- `GET|POST /api/v1/care-packs/followup?touchpoint_id=`

## Telemetría IA

| Modo | Contexto `AICostTracker` |
|------|--------------------------|
| Sync | `care-pack-assistance-batch`, `care-pack-followup-batch`, `care-pack-education-batch` |
| Vertex batch | `care-pack-vertex-batch` |

## Plan

`web/docs/plans/cohortes-asistencia-batch/`
