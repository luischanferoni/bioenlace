# Fase 2 — Aspectos, anclas y loaders

## Objetivo

Mapear `context_areas` → aspectos; ejecutar loaders; sin ensamblar prompt 2ª IA todavía (o solo en tests).

## Tareas

- [ ] Crear `AssistantContextHISAreaAspect` (enum + `area()`, `aspectKey()`, `priority()`).
- [ ] `AssistantContextAreaAspectMap` / métodos en enum área: candidatos por área.
- [ ] `AssistantContextAnchorResolver` + `AssistantContextAnchorBag`.
- [ ] `AssistantContextAreaAspectResolver` (tabla área + extracciones → plan).
- [ ] `AssistantContextAspectLoaderInterface` + `AssistantContextAspectLoaderRegistry`.
- [ ] Registrar loaders en `product-registries.php`.
- [ ] Implementar loaders MVP `appointments`:
  - [ ] `AppointmentCurrentAspectLoader` (`TurnoPacienteListadoService` / ancla)
  - [ ] `SiteAppointmentPoliciesAspectLoader` (`EfectorTurnosConfig` → JSON HIS)
  - [ ] `AppointmentSchedulingSetupAspectLoader` (agenda PES / formas_atencion)
  - [ ] `AppointmentHistorySubjectAtSiteAspectLoader` (list acotado)
- [ ] Ampliar categorías preprocess `tiempo` si aún no existe (coordinado con Fase 1).
- [ ] Tests: resolver aspectos tardanza vs “última vez”; ancla `site_id` desde cita próxima.

## Criterio de salida

Dado preprocess fixture, `assembleLoadPlan()` produce aspectos y loaders devuelven JSON con `scope_applied` en tests.
