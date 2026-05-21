# Fase 2 — `common/Clinical` (models + components)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 1](./01-foundation-db.md)  
**Estado:** pendiente

## Objetivo

Implementar capa PHP del dominio clínico: AR, DTOs FHIR, enums, servicios mínimos y repositorios. **Sin API pública aún.**

## Estructura a crear

```text
common/models/Clinical/
  Encounter.php
  EncounterDefinition.php
  CarePlan.php
  CarePlanActivity.php
  EpisodeOfCare.php
  Condition.php
  Goal.php
  MedicationRequest.php
  ServiceRequest.php
  …

common/components/Clinical/
  Dto/
  Enum/
  Service/
    CarePlanService.php
    EncounterLifecycleService.php
    EncounterAccessService.php
    PatientActiveCarePlanQuery.php
  Repository/
  Workflow/
    EncounterDocumentationService.php   # lógica ex ConsultaProcesamientoService
```

## Tareas

- [ ] AR con `rules()`, relaciones, soft-delete donde aplique (alinear con traits existentes).
- [ ] Enums: `CarePlanStatus`, `RequestStatus`, `EncounterStatus`, `ProcedureStatus`, etc. (vocabularios FHIR).
- [ ] DTOs readonly para respuestas API futuras (`CarePlanDto`, `EncounterDto`, …).
- [ ] `EncounterAccessService`: reemplazar reglas de `ConsultaAccessService` (paciente por `subject_persona_id`, médico por PES).
- [ ] `CarePlanService`: crear plan, agregar actividad, transiciones de estado (borrador → active → completed/revoked).
- [ ] `EncounterDocumentationService`: persistir documentación del encuentro desde payload estructurado (reemplazo de `ConsultaProcesamientoService`).
- [ ] Registrar namespaces en autoload si hace falta (Composer/Yii alias).

## Eliminar (cuando servicios nuevos pasen tests)

- [ ] `common/models/Consulta.php` y hijos listados en MIGRATION_STATUS.
- [ ] `common/components/Services/Consulta/`.

## Fuera de alcance

- Reordenar `Services/Turnos` (fase 3).
- Controllers API.
- Assistant.

## Definition of Done

- Tests unitarios mínimos: crear Encounter, CarePlan active, agregar MedicationRequest vía activity.
- Ningún import nuevo a clases `Consulta*` en código tocado (grep limpio en `Clinical/`).
- `MIGRATION_STATUS`: filas Model/Service en `hecho` para núcleo.

## Siguiente fase

[Fase 3 — Reordenar common](./03-reorder-common.md)
