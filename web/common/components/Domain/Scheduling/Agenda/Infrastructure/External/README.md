# Integraciones — Agendamiento FHIR (NIS)

Servidor: [NIS HAPI FHIR](https://nis.msalsgo.gob.ar/fhir)

Documentación de producto: [interoperabilidad-agendamiento-fhir.md](../../../../docs/producto/interoperabilidad-agendamiento-fhir.md)

| Carpeta | Rol |
|---------|-----|
| `Connector/` | HTTP FHIR R4 (GET + PUT Appointment) |
| `Contract/` | Puerto inbound connector |
| `Mapper/` | Appointment → inbound, status, bundle, PES/actor, códigos servicio |
| `Registry/` | Factory desde `params.fhirSchedulingInbound` |
| `Sync/` | Pull incremental, push status, espejo `turnos`, notify, reconcile |
| `Exception/` | Errores de conector |

Consola:

```bash
php yii fhir-scheduling-inbound/pull
php yii fhir-scheduling-inbound/push-outbound
php yii fhir-scheduling-inbound/reconcile-schedule-links
```

## HealthcareService codes (referencia)

Los mapeos operativos viven en `integration_fhir_service_code` (BD).
