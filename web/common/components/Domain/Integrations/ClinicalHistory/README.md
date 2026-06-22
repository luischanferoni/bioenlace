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
| Conector HTTP (stub) | `Connector/HttpNationalClinicalHistoryConnector` |
| Mapper Bundle | `Mapper/FhirClinicalHistoryBundleMapper` |

## Dominio (cola)

`common/components/Domain/Clinical/HistoryExchange/`

- `ClinicalHistoryOutboundEnqueueService` — hook desde `EncounterLifecycleService::finalize`
- `ClinicalHistoryOutboundProcessorService` — cron

## Cron

```bash
php yii clinical-history-exchange/process-outbound
php yii clinical-history-exchange/requeue <job_id>
```

## API staff

- `GET /api/v1/clinical/history-exchange/listar-por-encounter?encounter_id=`
- `GET /api/v1/clinical/history-exchange/ver-estado?job_id=`

## Params

`clinicalHistoryExchange` en `common/config/params.php` (master `enabled` default `false`).
