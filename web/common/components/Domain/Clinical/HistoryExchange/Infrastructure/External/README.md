# Integraciones — ClinicalHistory (export FHIR)

Export saliente de documentación clínica hacia servidor nacional / red jurisdiccional.

## Documentación de producto

Narrativa end-to-end: `web/docs/producto/interoperabilidad-historia-clinica.md`

## Piezas

| Rol | Clase |
|-----|--------|
| Contrato envío | `Contract/ClinicalHistoryExchangeConnector` |
| Contrato polling acuse | `Contract/ClinicalHistorySubmissionStatusConnector` |
| Registry | `ClinicalHistoryExchangeRegistry` |
| Conector off | `Connector/NullClinicalHistoryExchangeConnector` |
| Conector HTTP | `Connector/HttpNationalClinicalHistoryConnector` |
| Mapper Bundle | `Mapper/FhirClinicalHistoryBundleMapper` |

## Dominio (cola)

`common/components/Domain/Clinical/HistoryExchange/`

- `ClinicalHistoryOutboundEnqueueService` — hook desde `EncounterLifecycleService::finalize`
- `ClinicalHistoryOutboundProcessorService` — cron saliente
- `ClinicalHistoryOutboundReconcileService` — cron acuse
- `ClinicalHistoryOutboundRetryPolicy` — backoff

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
