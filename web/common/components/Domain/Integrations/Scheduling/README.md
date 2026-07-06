# Integraciones — Agendamiento FHIR (NIS)

Servidor: [NIS HAPI FHIR](https://nis.msalsgo.gob.ar/fhir)

Documentación de producto: [interoperabilidad-agendamiento-fhir.md](../../../../docs/producto/interoperabilidad-agendamiento-fhir.md)

| Componente | Rol |
|------------|-----|
| `MsalNisFhirSchedulingConnector` | HTTP FHIR R4 (GET + PUT Appointment) |
| `FhirSchedulingConnectorRegistry` | Factory desde `params.fhirSchedulingInbound` |
| `FhirSchedulePesResolver` | Schedule → PES (confianza) |
| `FhirHealthcareServiceCodeCatalog` | Código servicio → `id_servicio` |
| `TurnoInboundSyncService` | Appointment → espejo `turnos` |
| `FhirSchedulingInboundPullService` | Pull incremental |
| `FhirAppointmentOutboundSyncService` | Push `Appointment.status` |
| `TurnoFhirOutboundNotifier` | Hook post-cambio de estado |
| `IntegrationScheduleLinkService` | Onboarding verificado |
| `FhirScheduleLinkReconcileService` | Detecta links `stale` |

Plan de construcción (interno): `web/docs/plans/fhir-scheduling-inbound/`

Consola:

```bash
php yii fhir-scheduling-inbound/pull
php yii fhir-scheduling-inbound/push-outbound
php yii fhir-scheduling-inbound/reconcile-schedule-links
```
