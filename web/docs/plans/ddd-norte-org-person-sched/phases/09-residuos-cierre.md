# Fase 09 — Residuos + cierre

## UseCases

| Antes | Después |
|-------|---------|
| `CareFollowupSchedulerService` | `ScheduleCareFollowup` ✅ |
| `ClinicalHistoryOutboundReconcileService` | `ReconcileClinicalHistoryOutbound` ✅ |
| `LaboratorySyncBatchService` | `SyncLaboratoryBatch` ✅ |
| `EmergencyMetricsMaterializeService` | `MaterializeEmergencyMetrics` ✅ |
| `CareRequestActCodingService` | `CodeCareRequestAct` ✅ |
| `PrescriptionRdiPreSubmitValidationService` | `ValidatePrescriptionRdiPreSubmit` ✅ |
| `TreatmentRequestSnomedCodingService` | `CodeTreatmentRequestSnomed` ✅ |
| `LaboratoryEncounterLinkPendingService` | `ResolvePendingLaboratoryEncounterLink` ✅ |

## Domain

- `CarePackConfig` permanece en `Application/Service` (lee `Yii::$app->params`; no bajar a Domain).

## Cierre del plan

Las intenciones claras de Org/Person/Sched/Clinical ya están en `UseCase/`.  
Queda fuera de alcance (consciente): catalogs, query/listados, UI flows, seeds, depdrops.
