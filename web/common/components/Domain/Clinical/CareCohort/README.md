# CareCohort — packs por similitud de perfil

Generación en cola de packs reutilizables (`assistance_questions`, `followup_program`, `education_bundle`) keyed por `cohort_key` (SHA-256 de perfil estable).

## Configuración

| App | Archivo | Notas |
|-----|---------|--------|
| Definición web | `common/config/params-care-cohort.php` | Frontend/admin vía common |
| Frontend / API | `frontend/config/params.php` | `care_cohort.enabled = true` |
| **Consola / cron** | `console/config/params.php` + `params-local.php` | **No** usa `common/params.php` |
| Prod GCP / GCS | `console/config/params-local.php` | Obligatorio para generación IA en cron |

Documentación: `web/docs/producto/asistencia-cohortes.md`

## Cron

```bash
php yii care-pack/run-jobs          # cada 5 min
php yii care-pack/poll-vertex       # cada 15 min — Vertex batch
php yii care-pack/vertex-status
```
