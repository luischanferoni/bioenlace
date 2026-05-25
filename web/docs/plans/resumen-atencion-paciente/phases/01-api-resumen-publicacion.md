# Fase 1 — API resumen y publicación

## Objetivo

Snapshot del resumen IA, endpoints paciente y job post-`close`.

## Checklist

- [x] Migración: `encounter_patient_summary` + `encounter_patient_summary_publish_queue`
- [x] `PatientEncounterSummaryBuilder` — `encounter.note`, efector, profesional, recetas emitidas, pedidos
- [x] Hook en `EncounterLifecycleService::finalize` → cola T+3 min
- [x] `php yii encounter-patient-summary/run` + `publish <id>`
- [x] `GET listar-atenciones-como-paciente` (paginado, solo publicados)
- [x] `GET ver-resumen-como-paciente?encounter_id=`
- [x] `GET ultima-atencion-como-paciente`
- [x] RBAC + rutas `main.php`
- [x] Push `ENCOUNTER_SUMMARY_READY` al publicar (adelanto Fase 2)
- [ ] Tests manuales: finalizar encounter AMB con `note` + cron run

## Contrato resumen (mínimo)

```json
{
  "encounterId": 123,
  "periodEnd": "2026-05-20T14:30:00+00:00",
  "efector": { "id": 1, "nombre": "..." },
  "profesional": { "display": "..." },
  "narrativeText": "Texto IA (snapshot)...",
  "publishedAt": "2026-05-20T14:35:00+00:00"
}
```

## Criterio de cierre

Paciente autenticado obtiene última atención y detalle por API; snapshot escrito tras job.
