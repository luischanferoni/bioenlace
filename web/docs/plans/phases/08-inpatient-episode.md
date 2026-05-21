# Fase 8 — Internación (EpisodeOfCare)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 5](./05-care-plan-lifecycle.md), [Fase 6](./06-orders-medication-practice.md)  
**Estado:** pendiente

## Objetivo

Unificar internación bajo **EpisodeOfCare + Encounter IMP + CarePlan inpatient**; eliminar isla `seg_nivel_internacion_*` para órdenes clínicas.

## Mapeo

| Hoy | Objetivo |
|-----|----------|
| `seg_nivel_internacion` | `episode_of_care` (+ FK a cama/efector según modelo actual) |
| `seg_nivel_internacion_medicamento` | `medication_request` (category inpatient) |
| `seg_nivel_internacion_practica` | `service_request` |
| `seg_nivel_internacion_diagnostico` | `condition` |
| `consultas_regimen` en internación | `nutrition_order` |
| Múltiples Encounter durante estancia | `episode_of_care` agrupa encounters |

## Integración alta

- [ ] `InternacionController` o API equivalente llama `CarePlanLifecycleService::completeOnDischarge`.
- [ ] Epicrisis / resumen: `ClinicalImpression` o `DocumentReference` (fase posterior si hace falta).

## API

- [ ] Endpoints staff para listar planes/órdenes activas por episodio de internación.
- [ ] Paciente: ver plan activo de internación si aplica (producto).

## Fuera de alcance

- Facturación consumos (`seg_nivel_internacion_consumo`) — otro dominio.

## Definition of Done

- Admisión → alta en staging con un solo episodio y care plan inpatient cerrado en alta.
- Sin escrituras a `seg_nivel_internacion_medicamento` / `_practica`.

## Siguiente fase

[Fase 9 — Asistente](./09-assistant.md)
