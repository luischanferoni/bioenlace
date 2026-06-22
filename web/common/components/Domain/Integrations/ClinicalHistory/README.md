# Integraciones — ClinicalHistory (export FHIR)

Export saliente de documentación clínica hacia servidor nacional / red jurisdiccional.

## Plan

`web/docs/plans/interoperabilidad-historia-clinica/`

## Piezas

| Rol | Clase |
|-----|--------|
| Contrato | `Contract/ClinicalHistoryExchangeConnector` |
| Registry | `ClinicalHistoryExchangeRegistry` |
| Conector off | `Connector/NullClinicalHistoryExchangeConnector` |
| Conector HTTP | `Connector/HttpNationalClinicalHistoryConnector` |
| Polling acuse | `Contract/ClinicalHistorySubmissionStatusConnector` |
| Mapper Bundle | `Mapper/FhirClinicalHistoryBundleMapper` |
| Reconcile | `HistoryExchange/ClinicalHistoryOutboundReconcileService` |

## Dominio (cola)

`common/components/Domain/Clinical/HistoryExchange/`

- `ClinicalHistoryOutboundEnqueueService` — hook desde `EncounterLifecycleService::finalize`
- `ClinicalHistoryOutboundProcessorService` — cron saliente
- `ClinicalHistoryOutboundReconcileService` — cron acuse (Fase 4)

## Cron

```bash
php yii clinical-history-exchange/process-outbound
php yii clinical-history-exchange/reconcile
php yii clinical-history-exchange/requeue <job_id>
```

## API staff

- `GET /api/v1/clinical/history-exchange/listar-por-encounter?encounter_id=`
- `GET /api/v1/clinical/history-exchange/ver-estado?job_id=`

## Params

`clinicalHistoryExchange` en `common/config/params.php` (master `enabled` default `false`).
