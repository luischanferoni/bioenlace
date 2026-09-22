# Design — ddd-norte-org-person-sched

## Mapa UseCase fase 01–02

| Antes (`Application/Service`) | Después (`Application/UseCase`) |
|-------------------------------|---------------------------------|
| `ProfesionalEfectorServicioAltaService` | `EnsurePesAssignment` ✅ |
| `ProfesionalEfectorServicioBajaService` | `DeactivatePesAssignment` ✅ |
| `InstitutionalEfectorSignupService` | `SignUpInstitutionalEfector` ✅ |
| `MinistrySignupRequestService` | `RequestMinistrySignup` ✅ |
| `RegistroService` | `RegisterPerson` ✅ |
| `RegistroStaffPacienteService` | `RegisterPatientByStaff` ✅ |
| `PersonaIdentidadBasicaUpdateService` | `UpdateBasicIdentity` ✅ |
| `FrontDeskSessionService` | `EstablishFrontDeskSession` ✅ |
| `AdminEfectorAsignacionService` | `EnsureAdminEfectorAssignment` ✅ |
| `BillingMembershipSwitchService` | `SwitchBillingMembership` ✅ |
| `SesionOperativaService` | `EstablishOperativeSession` ✅ |
| `PersonaIdentidadPendienteService` | `CreatePendingIdentity` ✅ |
| `PersonaIdentidadResolverService` | `ResolvePersonIdentity` ✅ |
| `PatientDelegationService` | `DesignatePatientDelegation` ✅ |
| `VerifiedGuardianshipService` | `EstablishVerifiedGuardianship` ✅ |
| `ProfesionalHorarioActivaService` | `ActivateProfesionalHorario` ✅ |
| `SesionOperativaProfesionalHabilitacionService` | `ResolveOperativeProfessionalEligibility` ✅ |
| `CarePackGenerationService` | `GenerateCarePack` ✅ |
| `CarePackJobEnqueueService` | `EnqueueCarePackJob` ✅ |
| `EncounterLifecycleService` | `AdvanceEncounterLifecycle` ✅ |
| `ConditionLifecycleService` | `AdvanceConditionLifecycle` ✅ |
| `PatientEncounterSummaryPublishService` | `PublishPatientEncounterSummary` ✅ |
| `EncounterReasonService` | `RecordEncounterReason` ✅ |
| `EpisodeOfCareService` | `ManageEpisodeOfCare` ✅ |
| `CarePlanLifecycleService` | `AdvanceCarePlanLifecycle` ✅ |
| `ReferralRequestService` | `CreateReferralRequest` ✅ |
| `MedicationRequestService` | `ManageMedicationRequest` ✅ |
| `ServiceRequestService` | `ManageServiceRequest` ✅ |
| `EmergencyIntakeService` | `IntakeEmergencyEpisode` ✅ |
| `EmergencyTriageService` | `TriageEmergencyEpisode` ✅ |
| `EmergencyInpatientTransferService` | `TransferEmergencyToInpatient` ✅ |
| `InpatientAdmissionService` | `AdmitInpatient` ✅ |
| `InpatientBedTransferService` | `TransferInpatientBed` ✅ |
| `InpatientDischargeStructuredService` | `DischargeInpatient` ✅ |
| `EncounterAutomaticCodingService` | `ApplyEncounterAutomaticCoding` ✅ |
| `EncounterJourneyService` | `OrchestrateEncounterJourney` ✅ |
| `EncounterDocumentationService` | `DocumentEncounter` ✅ |
| `CarePlanReminderScheduleService` | `ScheduleCarePlanReminders` ✅ |
| `CareProtocolMatcherService` | `MatchCareProtocol` ✅ |
| `EmergencyDischargeStructuredService` | `DischargeEmergencyEpisode` ✅ |
| `EmergencyEncounterOutcomeService` | `RecordEmergencyEncounterOutcome` ✅ |
| `PostDischargeFollowupSchedulerService` | `SchedulePostDischargeFollowup` ✅ |
| `InpatientOrderService` | `ManageInpatientOrder` ✅ |
| `ElectronicPrescriptionService` | `IssueElectronicPrescription` ✅ |
| `LaboratoryIngestService` | `IngestLaboratoryResults` ✅ |
| `LaboratoryEncounterLinkService` | `LinkLaboratoryToEncounter` ✅ |
| `LegalExportRequestService` | `RequestLegalExport` ✅ |
| `LegalExportProcessorService` | `ProcessLegalExport` ✅ |
| `ClinicalHistoryOutboundEnqueueService` | `EnqueueClinicalHistoryOutbound` ✅ |
| `ClinicalHistoryOutboundProcessorService` | `ProcessClinicalHistoryOutbound` ✅ |
| `CareRequestService` | `ResolveCareRequest` ✅ |
| `CareRequestPatientService` | `SubmitPatientCareRequest` ✅ |

Métodos públicos se mantienen cuando es posible (`ensurePersonaServicioEnEfector`, `registrar`, `update`, …) para no reescribir el cuerpo; solo cambia FQCN + carpeta.

## Agenda Infra (fase 03)

```text
Agenda/Infrastructure/
  Persistence/          # NullEfectorDirectionsProvider (stub local)
  External/
    Outbound/           # TurnoOutboundChannel (email/SMS stub)
    NisFhir/            # Connector, Contract, Mapper, Registry, Sync, Exception
```

Port: `Domain/Port/EfectorDirectionsProvider` — toda implementacion y consumer con `use` explícito.

## Riesgos

- Autoload / params `fhirSchedulingInbound.class` → actualizar path tras mv NisFhir.
- Controllers API + product-registries + hydrators: grep exhaustivo al renombrar.
- Índice Cursor puede mostrar paths fantasma (`Scheduling/Infrastructure/`); source of truth = disco.
