# Fase 08 — Journey, reminders, Rx, Lab, Legal, History, CareRequest

## Encounter

| Antes | Después |
|-------|---------|
| `EncounterAutomaticCodingService` | `ApplyEncounterAutomaticCoding` ✅ |
| `EncounterJourneyService` | `OrchestrateEncounterJourney` ✅ |
| `EncounterDocumentationService` | `DocumentEncounter` ✅ |

## CarePlan

| Antes | Después |
|-------|---------|
| `CarePlanReminderScheduleService` | `ScheduleCarePlanReminders` ✅ |
| `CareProtocolMatcherService` | `MatchCareProtocol` ✅ |

## Emergency / Inpatient

| Antes | Después |
|-------|---------|
| `EmergencyDischargeStructuredService` | `DischargeEmergencyEpisode` ✅ |
| `EmergencyEncounterOutcomeService` | `RecordEmergencyEncounterOutcome` ✅ |
| `PostDischargeFollowupSchedulerService` | `SchedulePostDischargeFollowup` ✅ |
| `InpatientOrderService` | `ManageInpatientOrder` ✅ |

## Prescription / Laboratory / Legal / History / CareRequest

| Antes | Después |
|-------|---------|
| `ElectronicPrescriptionService` | `IssueElectronicPrescription` ✅ |
| `LaboratoryIngestService` | `IngestLaboratoryResults` ✅ |
| `LaboratoryEncounterLinkService` | `LinkLaboratoryToEncounter` ✅ |
| `LegalExportRequestService` | `RequestLegalExport` ✅ |
| `LegalExportProcessorService` | `ProcessLegalExport` ✅ |
| `ClinicalHistoryOutboundEnqueueService` | `EnqueueClinicalHistoryOutbound` ✅ |
| `ClinicalHistoryOutboundProcessorService` | `ProcessClinicalHistoryOutbound` ✅ |
| `CareRequestService` | `ResolveCareRequest` ✅ |
| `CareRequestPatientService` | `SubmitPatientCareRequest` ✅ |

## Notas

- ~41 callers PHP actualizados.
- Quedan en Service: catalogs, query/listados, UI, seeds, resolvers de fase/ventana.
- Cierre razonable del plan `ddd-norte-org-person-sched` salvo commit/deploy.
