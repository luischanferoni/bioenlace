# Fase 10 — Flutter paciente (care plans activos)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 4](./04-api-clinical-core.md), [Fase 5](./05-care-plan-lifecycle.md)  
**Estado:** pendiente

## Objetivo

Consumir `GET /api/v1/clinical/care-plans/active` en inicio del paciente y pantallas de detalle.

## Tareas

- [ ] `TratamientoService` o ampliar `AsistenteService` en `mobile/packages/shared` (preferible servicio dedicado `CarePlanService`).
- [ ] `HomeScreen`: card “Tu tratamiento” con resumen de actividades.
- [ ] Modelos Dart: DTOs alineados a JSON API (`CarePlan`, activities polimórficas).
- [ ] Reemplazar referencias `id_consulta` en chat/motivos por `encounter_id` donde API ya cambió.
- [ ] App médico: misma API si staff debe ver plan del paciente (opcional en esta fase).

## Fuera de alcance

- UI JSON embebida clínica completa (fase 11).

## Definition of Done

- Paciente con plan activo de prueba ve card en inicio.
- Paciente sin plan no ve sección vacía ruidosa.

## Siguiente fase

[Fase 11 — UI JSON clínica](./11-ui-json-clinical.md)
