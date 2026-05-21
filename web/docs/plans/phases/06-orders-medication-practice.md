# Fase 6 — Órdenes: medicación, prácticas, derivaciones

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 4](./04-api-clinical-core.md), [Fase 5](./05-care-plan-lifecycle.md)  
**Estado:** hecho (2026-05-20)

## Objetivo

Implementar recursos de **órdenes** y su vínculo a CarePlan: medicación, estudios, interconsultas, administración.

## Alcance

| Recurso | Service | API (opcional en esta fase) |
|---------|---------|------------------------------|
| MedicationRequest | `MedicationRequestService` | POST bajo encounter / care-plan |
| ServiceRequest | `ServiceRequestService` | lab, imagen, kinesio, referral |
| Procedure | `ProcedureService` | transición desde service request |
| MedicationAdministration | `MedicationAdministrationService` | suministro enfermería |
| NutritionOrder | `NutritionOrderService` | ex régimen |

## Reglas

- Toda orden nueva en contexto ambulatorio/internación se registra como **CarePlanActivity** (+ fila en tabla de orden).
- Estados FHIR únicos (no `ACTIVO`/`SUSPENDIDO` paralelos).
- SNOMED: reutilizar `Terminology` existente.

## Encounter documentation

- [x] `EncounterDocumentationService` persiste vía `MedicationRequestService` / `ServiceRequestService` (payload IA `ConsultaMedicamentos`, `ConsultaPracticas`, `ConsultaDerivaciones`).
- [ ] `EncounterDefinition.workflow_json` declara pasos permitidos por servicio (mejora posterior).

## Fuera de alcance

- Odontología pieza a pieza (fase 7).
- DeviceRequest / lentes.

## Definition of Done

- Flujo end-to-end en API: encounter → guardar → care plan con ≥1 medication_request y ≥1 service_request.
- Tablas legacy de órdenes sin escritores en código (grep).

## Siguiente fase

[Fase 7 — Especialidades](./07-specialties.md)
