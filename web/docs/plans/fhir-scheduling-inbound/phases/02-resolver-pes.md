# Fase 2 — Resolver PES y onboarding

## Resolver compuesto

`FhirSchedulePesResolver::resolveFromScheduleActors()`:

1. Extraer CUIL/DNI, SISA, código servicio del `Schedule` (vía includes HAPI).
2. `Persona` por CUIL; efector por `codigo_sisa`; servicio por catálogo.
3. `ProfesionalEfectorServicio::findOneActivoPorPersonaEfectorServicio`.
4. Si único → candidato `provisional`; si no → `unresolved`.

## Onboarding verificado

- Pantalla staff: Schedule HAPI + actores resueltos + PES candidato.
- Confirmación → `integration_schedule_link` con `status=verified` y fingerprint.
- Reconciliación: comparar fingerprint en cada sync; divergencia → `stale`.

## Entregables

- `FhirScheduleOnboardingUiService` + UI JSON.
- Job `FhirScheduleLinkReconcileService`.
- Golden tests con Bundle fixture acordado con HAPI.
