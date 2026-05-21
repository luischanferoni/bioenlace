# Fase 4 — API clínica núcleo (Encounter + CarePlan)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 2](./02-common-clinical.md), [Fase 3](./03-reorder-common.md)  
**Estado:** pendiente

## Objetivo

Exponer el dominio Clinical vía API v1; reemplazar endpoints y servicios que hoy usan `Consulta*`.

## Controllers nuevos

```text
frontend/modules/api/v1/controllers/clinical/
  EncounterController.php      # analizar/guardar documentación (ex ConsultaController)
  CarePlanController.php       # list active, view, revoke, complete
  ConditionController.php      # CRUD motivos/diagnósticos en encounter
```

## Rutas (`urlManager`)

Propuesta (sin retrocompat):

| Método | Ruta | Acción |
|--------|------|--------|
| POST | `/api/v1/clinical/encounter/analizar` | IA / preproceso documentación |
| POST | `/api/v1/clinical/encounter/guardar` | Persistir encounter + care plan draft |
| GET | `/api/v1/clinical/care-plans/active` | Planes activos del paciente autenticado |
| GET | `/api/v1/clinical/care-plans/<id>` | Detalle con activities expandidas |
| POST | `/api/v1/clinical/care-plans/<id>/complete` | Cierre manual |
| POST | `/api/v1/clinical/care-plans/<id>/revoke` | Suspensión |

## Reemplazos

| Eliminar / dejar de usar | Usar |
|--------------------------|------|
| `api/v1/ConsultaController` | `clinical/EncounterController` |
| `ConsultaAccessService` en Motivos/Pacientes/Chat | `EncounterAccessService` |
| Referencias `id_consulta` en body | `encounter_id` |

## RBAC

- [ ] Permisos ApiGhost: `/api/clinical/encounter/*`, `/api/clinical/care-plans/*`.
- [ ] Paciente: solo `subject` = su `id_persona`; staff según PES/efector en encounter.

## Respuesta JSON

- Formato alineado a DTOs (`resourceType` opcional pero recomendado).
- HTTP 400 con mensaje claro si falta contexto (no revalidar identidad fuera de filtros auth).

## Fuera de alcance

- Órdenes detalladas (fase 6).
- UI JSON clínica (fase 11).
- Flutter (fase 10).

## Definition of Done

- Postman/colección: crear encounter, activar care plan, GET active como paciente.
- `MotivosConsultaController` y `ConsultaChatController` usan `Encounter` + access service nuevo.
- `ConsultaController` eliminado o vacío con 410 Gone.
- Tests API smoke (opcional pero recomendado).

## Siguiente fase

[Fase 5 — Ciclo de vida CarePlan](./05-care-plan-lifecycle.md)
