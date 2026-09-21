# Integraciones — Agendamiento FHIR (NIS)

Servidor: [NIS HAPI FHIR](https://nis.msalsgo.gob.ar/fhir)

Documentación de producto: [interoperabilidad-agendamiento-fhir.md](../../../../docs/producto/interoperabilidad-agendamiento-fhir.md)

| Componente | Rol |
|------------|-----|
| `Connector/MsalNisFhirSchedulingConnector` | HTTP FHIR R4 (GET + PUT Appointment) |
| `Service/FhirSchedulingConnectorRegistry` | Factory desde `params.fhirSchedulingInbound` |
| `Service/FhirSchedulePesResolver` | Schedule → PES (confianza) |
| `Service/FhirHealthcareServiceCodeCatalog` | Código servicio → `id_servicio` |
| `Service/TurnoInboundSyncService` | Appointment → espejo `turnos` |
| `Service/FhirSchedulingInboundPullService` | Pull incremental |
| `Service/FhirAppointmentOutboundSyncService` | Push `Appointment.status` |
| `Service/TurnoFhirOutboundNotifier` | Hook post-cambio de estado |
| `Service/IntegrationScheduleLinkService` | Onboarding verificado |
| `Service/FhirScheduleLinkReconcileService` | Detecta links `stale` |

Plan de construcción (interno): `web/docs/plans/fhir-scheduling-inbound/`

Consola:

```bash
php yii fhir-scheduling-inbound/pull
php yii fhir-scheduling-inbound/push-outbound
php yii fhir-scheduling-inbound/reconcile-schedule-links
```

## HealthcareService codes (referencia)

Los mapeos operativos viven en `integration_fhir_service_code` (BD).
Systems acordados con HAPI (semilla documental; cargar vía UI staff o seed):

- source_system: `fhir-default`
- SNOMED examples: General practice `394814009`, Psychiatry `394587001`
- URI Practitioner CUIL: `http://www.afip.gob.ar/cuil`
- URI DNI: `http://www.renaper.gob.ar/dni`

