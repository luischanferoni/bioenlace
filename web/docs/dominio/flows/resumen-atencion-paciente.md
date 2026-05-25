# Resumen de atención (paciente)

## Resumen

Tras finalizar un encounter **ambulatorio** (`AMB`, `finished`), el sistema programa la publicación del resumen (**T+3 min**). El texto narrativo es el **`encounter.note`** (salida IA / `texto_procesado` al guardar), no el dictado crudo del médico ni listados SNOMED.

## API

| Método | Ruta | Uso |
|--------|------|-----|
| GET | `/api/v1/clinical/encounter/listar-atenciones-como-paciente` | Lista paginada (`limit`, `offset`) |
| GET | `/api/v1/clinical/encounter/ver-resumen-como-paciente` | Detalle (`encounter_id`) |
| GET | `/api/v1/clinical/encounter/ultima-atencion-como-paciente` | Última publicada |

Permisos RBAC (ApiGhost): `/api/clinical/encounter-patient-summary/*`

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

Ver `web/docs/plans/resumen-atencion-paciente/` (fases UI asistente y vínculos lab pendientes).
