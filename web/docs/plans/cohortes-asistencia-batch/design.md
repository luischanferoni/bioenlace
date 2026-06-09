# Design — Cohortes y batch

## Cohorte

Vector estable → `cohort_key` (SHA-256 hex):

- `life_stage`: banda etaria (0-17, 18-39, 40-64, 65+)
- `sexo`: M / F / O / U
- `conditions`: hasta 3 términos normalizados (condiciones activas)
- `motive_cluster`: slug desde motivo/resumen o `general`
- `jurisdiction`: provincia vía localidad del efector o `unknown`
- `season`: trimestre calendario (contexto epidemiológico futuro)

Pacientes con el mismo `cohort_key` comparten `care_cohort_pack` por `pack_type`.

## Tipos de pack

| `pack_type` | Uso |
|-------------|-----|
| `assistance_questions` | Preguntas dinámicas pre-atención |
| `followup_program` | Calendario touchpoints + formularios |
| `education_bundle` | Módulos educativos reutilizables |

## Jobs

`care_pack_job`: `pending` → `running` → `completed` | `failed`

| `mode` | Cuándo |
|--------|--------|
| `sync` | Default: `IAManager::consultarIA` en cron (como motivos batch) |
| `vertex_batch` | Lote ≥ N jobs → JSONL en GCS → `batchPredictionJobs` |

## Runtime

1. Resolver `cohort_key` del paciente/encounter.
2. Buscar pack vigente (`expires_at` > now).
3. Si falta → encolar job (idempotente por `pack_type` + `cohort_key`).
4. Instancia encounter: `care_encounter_pack` enlaza `encounter_id` → pack ids.
5. UI lee JSON del pack; **sin IA** salvo `delta_adapt` (Fase 2+).

## Contextos IAManager (nuevos)

- `care-pack-assistance-batch`
- `care-pack-followup-batch`
- `care-pack-education-batch`

## Config

Definición compartida: `common/config/params-care-cohort.php` (incluida en `common/config/params.php`).

Activación por aplicación:

- Frontend / API: `frontend/config/params.php` (merge con common)
- Cron: `console/config/params.php` — **independiente** de common
- Vertex batch prod: `console/config/params-local.php`

Operación y crons: `web/docs/producto/asistencia-cohortes.md`
