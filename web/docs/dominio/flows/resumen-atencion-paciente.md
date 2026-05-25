# Resumen de atención (paciente)

## Resumen

Tras finalizar un encounter **ambulatorio** (`AMB`, `finished`), el sistema programa la publicación del resumen (**T+3 min**). El texto narrativo es el **`encounter.note`** (salida IA / `texto_procesado` al guardar), no el dictado crudo del médico ni listados SNOMED.

## API

| Método | Ruta | Uso |
|--------|------|-----|
| GET | `/api/v1/clinical/encounter/listar-atenciones-como-paciente` | Lista paginada (`limit`, `offset`) |
| GET | `/api/v1/clinical/encounter/ver-resumen-como-paciente` | Detalle (`encounter_id`) |
| GET | `/api/v1/clinical/encounter/ultima-atencion-como-paciente` | Última publicada |
| GET/POST | `/api/v1/clinical/encounter/mis-atenciones-como-paciente` | UI JSON listado (asistente) |
| GET/POST | `/api/v1/clinical/encounter/ver-resumen-atencion-como-paciente` | UI JSON detalle (`encounter_id`) |
| GET/POST | `/api/v1/clinical/encounter/ultima-atencion-ui-como-paciente` | UI JSON última atención |

Permisos RBAC (ApiGhost): `/api/clinical/encounter-patient-summary/*` (+ migración UI `m260601_100002`)

## App Flutter (paciente)

- Lista/detalle nativo: `encounter_summary_list_screen`, `encounter_summary_detail_screen`
- Push → detalle: `ENCOUNTER_SUMMARY_READY`
- Enlaces a receta/lab: `UiJsonScreen` con `detailApiRoute` del DTO

## Cola y cron

```bash
php yii migrate --migrationPath=@common/migrations
php yii encounter-patient-summary/run          # cron ~cada minuto
php yii encounter-patient-summary/publish 123  # publicación inmediata (prueba)
```

La publicación se encola en `finalize()` del encounter ambulatorio (`EncounterLifecycleService`).

## Push

Tipo FCM: `ENCOUNTER_SUMMARY_READY` — payload `{ encounter_id }` (sin PHI en el push).

## Código

- `common/components/Clinical/PatientSummary/`
- `frontend/modules/api/v1/controllers/clinical/EncounterPatientSummaryController.php`
- `console/controllers/EncounterPatientSummaryController.php`

## Plan de trabajo

Ver `web/docs/plans/resumen-atencion-paciente/` (Fases 3–4 cerradas; Fase 5 expediente legal staff pendiente).
