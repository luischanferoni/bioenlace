# Fase 2 — `common/Clinical` (models + components)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 1](./01-foundation-db.md)  
**Estado:** hecho (2026-05-20)

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

- [x] AR núcleo + órdenes principales en `common/models/Clinical/`.
- [x] Enums FHIR en `common/components/Clinical/Enum/`.
- [ ] DTOs readonly (`CarePlanDto`, `EncounterDto`) — fase API (4).
- [x] `EncounterAccessService`, `CarePlanService`, `EncounterLifecycleService`, `PatientActiveCarePlanQuery`.
- [x] `EncounterDocumentationService` (guardar diagnósticos/medicación/prácticas; analizar delega IA legacy).
- [x] `ConsultasConfiguracion` → alias de `EncounterDefinition`.
- [x] `ClinicalEncounterEntry` y motivos pre-consulta apuntan a Encounter.

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
