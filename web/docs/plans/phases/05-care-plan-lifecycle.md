# Fase 5 — Ciclo de vida CarePlan (crónicos, programas, alta)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 4](./04-api-clinical-core.md)  
**Estado:** pendiente

## Objetivo

Implementar reglas de negocio para **cuándo** un CarePlan está activo, pausado o terminado, incluyendo internación, ambulatorio, crónicos y programas.

## Categorías (`care_plan.category`) — lista cerrada v1

| Código | Uso |
|--------|-----|
| `acute-ambulatory` | Indicaciones de una consulta / episodio corto |
| `chronic` | Medicación o cuidado continuo |
| `program` | Kinesiología, psicología, ortodoncia (N sesiones) |
| `inpatient` | Plan durante internación |
| `postoperative` | Post cirugía / procedimiento |
| `preventive` | Prevención, controles |
| `palliative` | Cuidados paliativos |
| `odontology` | Plan odontológico multi-visita |
| `ophthalmology` | Seguimiento oftalmológico |
| `mental-health` | Salud mental / psicología |
| `rehabilitation` | Rehabilitación / kinesiología |
| `nutrition` | Plan nutricional |
| `other` | Residual documentado |

Ampliar según catálogo `servicios` / `encounter_class` sin crear tablas nuevas.

## Reglas de transición

### Internación

- Al **ingreso:** crear `EpisodeOfCare` + `CarePlan` (`category=inpatient`, `status=active`).
- Al **alta:** `EpisodeOfCare.status=finished`, Encounter IMP `finished`, **`CarePlan.status=completed`**, `period.end=fecha alta`.
- Excepción documentada: plan de continuidad ambulatoria → nuevo `CarePlan` `chronic` o `program` en lugar de extender el inpatient.

### Ambulatorio agudo

- Al abrir encounter: `CarePlan` `draft` o `active` según configuración del servicio.
- Al cerrar encounter: opción A) completar plan agudo; opción B) promover a `chronic` si médico marca “continuar tratamiento”.

### Crónico

- No se completa al cerrar un Encounter.
- Nuevo plan del mismo tipo puede **revocar** el anterior (`status=revoked`).

### Programa

- `ServiceRequest` o `Appointment` por sesión; contador `occurrenceCount`.
- Auto-`completed` cuando sesiones agotadas o `period.end` alcanzado.

## Servicios

- [ ] `CarePlanLifecycleService` (o métodos en `CarePlanService`): `completeOnDischarge`, `revoke`, `hold`, `activate`.
- [ ] Hooks desde internación (alta) y desde `EncounterLifecycleService::close`.

## API

- [ ] Endpoints de transición (fase 4) con validación de permisos.
- [ ] Mensajes de error claros si se intenta editar plan `completed`.

## Definition of Done

- Matriz de transición documentada en este archivo + tests unitarios por escenario (alta, cierre manual, programa agotado).
- `GET care-plans/active` excluye `completed` y `revoked`; incluye `on-hold` según producto.

## Siguiente fase

[Fase 6 — Órdenes](./06-orders-medication-practice.md)
