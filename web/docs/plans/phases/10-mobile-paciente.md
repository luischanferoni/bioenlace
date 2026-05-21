# Fase 10 — Flutter paciente (care plans activos)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 4](./04-api-clinical-core.md), [Fase 5](./05-care-plan-lifecycle.md)  
**Estado:** hecho (2026-05-20)

## Objetivo

Consumir `GET /api/v1/clinical/care-plans/active` en inicio del paciente y pantallas de detalle.

## Tareas

- [x] `CarePlanService` en `mobile/packages/shared/lib/clinical/care_plan_service.dart`.
- [x] `HomeScreen`: card “Tu tratamiento” con resumen (`activitySummaries`, `categoryLabel`).
- [x] Consumo JSON API (`CarePlan` + `activities` / resúmenes).
- [x] `encounter_id` en turnos API y lectura en home/chat (alias `id_consulta` mantenido).
- [ ] App médico: misma API si staff debe ver plan del paciente (opcional en esta fase).

## Fuera de alcance

- UI JSON embebida clínica completa (fase 11).

## Definition of Done

- Paciente con plan activo de prueba ve card en inicio.
- Paciente sin plan no ve sección vacía ruidosa.

## Siguiente fase

[Fase 11 — UI JSON clínica](./11-ui-json-clinical.md)
