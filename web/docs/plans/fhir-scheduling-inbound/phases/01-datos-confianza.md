# Fase 1 — Datos de confianza

## Hecho

- [x] `personas.cuil` (11 dígitos, único), `CuilValidator`, `PersonCuilService`
- [x] Alta PES exige CUIL (excepto servicio `AdminEfector`) — `ProfesionalEfectorServicioAltaService`
- [x] Flujo asistente `profesional-efector-servicio.crear-flow`: paso `capture_cuil` + API `cargar-cuil-profesional`
- [x] `integration_fhir_service_code` + `FhirHealthcareServiceCodeCatalog`
- [x] APIs `listar-codigos-servicio-fhir`, `guardar-codigo-servicio-fhir`
- [x] `integration_schedule_link` + `FhirSchedulePesResolver` + `ScheduleActorSet`
- [x] Export FHIR: CUIL en Patient/Practitioner (`FhirClinicalHistoryBundleMapper`)
- [x] Migraciones `m260706_130000`, `m260706_130001`

## Fase 2 — Hecho

- [x] Conector `MsalNisFhirSchedulingConnector` → NIS
- [x] Onboarding Schedule: `listar-schedules-hapi`, `preview-vinculo-schedule-hapi`, `confirmar-vinculo-schedule-hapi`
- [x] UI JSON: `views/json/organization/profesional-efector-servicio/preview-vinculo-schedule-hapi.json`, `confirmar-vinculo-schedule-hapi.json`
- [x] `FhirScheduleLinkReconcileService` + consola `reconcile-schedule-links`
- [x] Tests: `FhirSchedulePesResolverTest`, `FhirHealthcareServiceCodeCatalogTest`
- [ ] Golden tests con Bundle fixture real de NIS (cuando haya datos)

## Fase 3 — Hecho

- [x] Columnas turnos inbound + `integration_fhir_sync_state` — migración `m260706_140000`
- [x] RBAC onboarding — migración `m260706_140001`
- [x] `FhirSchedulingInboundPullService` + `TurnoInboundSyncService`
- [x] `FhirAppointmentOutboundSyncService` + `TurnoFhirOutboundNotifier`
- [x] Consola: `pull`, `push-outbound`, `reconcile-schedule-links`
- [x] Tests: `FhirAppointmentStatusMapperTest` (entrante y saliente)
- [ ] Cron en hosting + `fhirSchedulingInbound.enabled=true` en params-local de prod

## Pendiente operativo / QA

- [ ] Onboarding al menos un `Schedule` real en cada efector piloto
- [ ] Poblar catálogo `integration_fhir_service_code` por efector
- [ ] Validar pull/push con citas reales en NIS
