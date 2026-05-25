# Fase 1 — API resumen y publicación

## Objetivo

Snapshot del resumen IA, endpoints paciente y job post-`close`.

## Checklist

- [ ] Migración: `encounter_patient_summary` (o columnas en `encounter`: `patient_summary_text`, `patient_summary_published_at`, `patient_summary_version`)
- [ ] `PatientEncounterSummaryBuilder` — lee `encounter.note`, metadatos efector/profesional, artefactos básicos
- [ ] Hook en `EncounterLifecycleService::close` → encolar job con `run_at = now + Δ`
- [ ] Job: validar `AMB` + `finished`; no publicar si cancelado/reabierto según regla
- [ ] `GET listar-atenciones-como-paciente` (paginado, solo publicados o todos finished con flag)
- [ ] `GET ver-resumen-como-paciente?encounter_id=`
- [ ] `GET ultima-atencion-como-paciente`
- [ ] RBAC rutas ApiGhost + migración auth_item
- [ ] Reglas en `main.php`
- [ ] Tests manuales: cerrar encounter demo con `note` poblado

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
