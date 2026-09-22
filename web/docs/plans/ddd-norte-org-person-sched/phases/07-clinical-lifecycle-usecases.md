# Fase 07 — Encounter / CarePlan / Emergency / Inpatient UseCases

## Encounter

| Antes | Después |
|-------|---------|
| `EncounterLifecycleService` | `AdvanceEncounterLifecycle` ✅ |
| `ConditionLifecycleService` | `AdvanceConditionLifecycle` ✅ |
| `PatientEncounterSummaryPublishService` | `PublishPatientEncounterSummary` ✅ |
| `EncounterReasonService` | `RecordEncounterReason` ✅ |
| `EpisodeOfCareService` | `ManageEpisodeOfCare` ✅ |

## CarePlan

| Antes | Después |
|-------|---------|
| `CarePlanLifecycleService` | `AdvanceCarePlanLifecycle` ✅ |
| `ReferralRequestService` | `CreateReferralRequest` ✅ |
| `MedicationRequestService` | `ManageMedicationRequest` ✅ |
| `ServiceRequestService` | `ManageServiceRequest` ✅ |

## Emergency / Inpatient

| Antes | Después |
|-------|---------|
| `EmergencyIntakeService` | `IntakeEmergencyEpisode` ✅ |
| `EmergencyTriageService` | `TriageEmergencyEpisode` ✅ |
| `EmergencyInpatientTransferService` | `TransferEmergencyToInpatient` ✅ |
| `InpatientAdmissionService` | `AdmitInpatient` ✅ |
| `InpatientBedTransferService` | `TransferInpatientBed` ✅ |
| `InpatientDischargeStructuredService` | `DischargeInpatient` ✅ |

## Notas

- Callers API/controllers/tests actualizados (~59 archivos).
- `use` cruzados Service↔UseCase añadidos donde el cambio de namespace los rompía.
- `AdvanceCarePlanLifecycle` usa `match` PHP 8 (ya previo); smoke local 7.4 no lo parsea.
