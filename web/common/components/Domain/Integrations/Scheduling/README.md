# Integraciones — Agendamiento FHIR entrante

Servidor: [NIS HAPI FHIR](https://nis.msalsgo.gob.ar/fhir)

| Componente | Rol |
|------------|-----|
| `MsalNisFhirSchedulingConnector` | HTTP FHIR R4 |
| `FhirSchedulingConnectorRegistry` | Factory desde params |
| `FhirSchedulePesResolver` | Schedule → PES (confianza) |
| `FhirHealthcareServiceCodeCatalog` | Código servicio → `id_servicio` |
| `TurnoInboundSyncService` | Appointment → `turnos` espejo |
| `FhirSchedulingInboundPullService` | Pull incremental |
| `IntegrationScheduleLinkService` | Onboarding verificado |
| `FhirScheduleLinkReconcileService` | Detecta links stale |

Plan: `web/docs/plans/fhir-scheduling-inbound/`

Consola:

```bash
php yii fhir-scheduling-inbound/pull
php yii fhir-scheduling-inbound/reconcile-schedule-links
```
