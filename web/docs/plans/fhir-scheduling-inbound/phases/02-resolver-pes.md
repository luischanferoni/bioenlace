# Fase 2 — Resolver PES y onboarding

## Resolver compuesto

`FhirSchedulePesResolver::resolve()` (actores vía `ScheduleActorSet`):

1. Extraer CUIL/DNI, SISA, código servicio del `Schedule` (includes HAPI: `Schedule:actor`).
2. `Persona` por CUIL; efector por `codigo_sisa`; servicio por `FhirHealthcareServiceCodeCatalog`.
3. `ProfesionalEfectorServicio` activo para la terna persona + efector + servicio.
4. Si link verificado en catálogo y fingerprint OK → `verified`.
5. Si único candidato sin verificación → `provisional`; si ambigüedad → `unresolved`.

## Onboarding verificado (staff)

Flujo API + UI JSON (`FhirScheduleOnboardingUiService`):

| Acción | Endpoint |
|--------|----------|
| Listar agendas HAPI | `GET\|POST /api/v1/profesional-efector-servicio/listar-schedules-hapi` |
| Preview actores + PES candidato | `GET\|POST …/preview-vinculo-schedule-hapi` |
| Confirmar vínculo | `GET\|POST …/confirmar-vinculo-schedule-hapi` |

Confirmación persiste `integration_schedule_link` con `status=verified` y `actor_fingerprint`.

## Reconciliación

`FhirScheduleLinkReconcileService` compara fingerprint actual vs catálogo; divergencia → `stale`.

```bash
php yii fhir-scheduling-inbound/reconcile-schedule-links 100
```

Recomendado: cron diario (madrugada).

## Entregables

| Ítem | Estado |
|------|--------|
| `FhirScheduleOnboardingUiService` + UI JSON | Hecho |
| `IntegrationScheduleLinkService` | Hecho |
| Job reconcile + consola | Hecho |
| `FhirSchedulePesResolverTest` | Hecho |
| Golden tests Bundle HAPI | Pendiente |
