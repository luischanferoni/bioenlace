# Fase 1 — Datos de confianza

## Hecho

- [x] `personas.cuil`, alta PES, flujo asistente `capture_cuil`
- [x] `integration_fhir_service_code` + APIs catálogo
- [x] `integration_schedule_link` + resolver fail-closed
- [x] Migraciones `m260706_130000`, `m260706_130001`

## Fase 2 — En curso

- [x] Conector `MsalNisFhirSchedulingConnector` → NIS
- [x] Onboarding Schedule: preview + confirmar vínculo
- [x] `FhirScheduleLinkReconcileService`
- [ ] Golden tests con Bundle fixture real de NIS (cuando haya datos)

## Fase 3 — En curso

- [x] Columnas turnos inbound + `integration_fhir_sync_state`
- [x] `FhirSchedulingInboundPullService` + `TurnoInboundSyncService`
- [x] Consola `php yii fhir-scheduling-inbound/pull`
- [ ] Cron en hosting + `fhirSchedulingInbound.enabled=true` en params-local
- [ ] Actualización estados salientes Bioenlace → FHIR (PATCH Appointment)
