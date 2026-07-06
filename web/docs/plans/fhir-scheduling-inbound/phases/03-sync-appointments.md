# Fase 3 — Sync Appointments ↔ turnos

## Alcance

- Conector pull HAPI (`Appointment?_lastUpdated=…`).
- Mapper: `external_appointment_id`, `fhir_status`, refs Schedule.
- Resolver PES vía fase 2; `id_persona` opcional en paciente desconocido.
- **Saliente:** actualización `Appointment.status` en NIS cuando cambia `turnos.estado`.

## Columnas turnos

Migración `m260706_140000_turnos_fhir_inbound_columns`:

- `external_appointment_id`, `appointment_source_system`, `external_schedule_id`, `pes_resolution_trust`
- `fhir_status`, `appointment_type` (migración previa `m260520_100003`)
- `id_persona` nullable para citas sin paciente local

## Mapeo estados

| Interno | FHIR saliente |
|---------|----------------|
| PENDIENTE, EN_RESOLUCION | `booked` |
| EN_ATENCION | `arrived` |
| ATENDIDO | `fulfilled` |
| CANCELADO | `cancelled` |
| SIN_ATENDER | `noshow` |

Entrante: `FhirAppointmentStatusMapper::mapToTurnoEstado()`.

Saliente: `FhirAppointmentStatusMapper::mapTurnoEstadoToFhir()` + `FhirAppointmentOutboundSyncService`.

Hooks: `TurnoFhirOutboundNotifier` desde `TurnoLifecycleService`, resolución, bulk cancel y métodos estáticos del modelo.

## Jobs consola

```bash
php yii fhir-scheduling-inbound/pull 50
php yii fhir-scheduling-inbound/push-outbound 100
php yii fhir-scheduling-inbound/reconcile-schedule-links
```

## Activación (`params-local.php`)

```php
'fhirSchedulingInbound' => [
    'enabled' => true,
    'outbound' => ['enabled' => true],
],
```

## Cron sugerido

- Pull incremental cada 10 min.
- Push outbound cada hora (o confiar en hooks en tiempo real).
- Reconciliación links `stale` diaria.
