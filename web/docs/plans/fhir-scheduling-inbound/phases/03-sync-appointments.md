# Fase 3 — Sync Appointments ↔ turnos

## Alcance

- Pull incremental HAPI (`Appointment?_lastUpdated=…`).
- Mapper: `external_appointment_id`, `fhir_status`, refs `Schedule`.
- Resolver PES vía fase 2; `id_persona` opcional si paciente desconocido localmente.
- Push saliente: `Appointment.status` en NIS cuando cambia `turnos.estado` en Bioenlace.

## Columnas `turnos`

Migración `m260706_140000_turnos_fhir_inbound_columns`:

| Columna | Uso |
|---------|-----|
| `external_appointment_id` | `Appointment.id` en NIS |
| `appointment_source_system` | Clave conector (`msal-nis`) |
| `external_schedule_id` | `Schedule.id` HAPI |
| `pes_resolution_trust` | `verified` \| `provisional` \| `unresolved` \| `stale` |
| `fhir_status` | último `Appointment.status` conocido |
| `appointment_type` | tipo FHIR (migración previa `m260520_100003`) |

Índice único: `(appointment_source_system, external_appointment_id)`.

Tabla de cursor: `integration_fhir_sync_state` (`last_cursor` = instante `_lastUpdated` del último pull OK).

## Mapeo estados

### Entrante (`mapToTurnoEstado`)

| FHIR | `turnos.estado` |
|------|-----------------|
| `booked`, `pending`, `proposed`, `waitlist` | `PENDIENTE` |
| `arrived`, `checked-in` | `EN_ATENCION` |
| `fulfilled` | `ATENDIDO` |
| `cancelled`, `entered-in-error` | `CANCELADO` |
| `noshow` | `SIN_ATENDER` |

### Saliente (`mapTurnoEstadoToFhir`)

| `turnos.estado` | FHIR |
|-----------------|------|
| `PENDIENTE`, `EN_RESOLUCION` | `booked` |
| `EN_ATENCION` | `arrived` |
| `ATENDIDO` | `fulfilled` |
| `CANCELADO` | `cancelled` |
| `SIN_ATENDER` | `noshow` |

Solo turnos con `external_appointment_id` y `appointment_source_system` participan del push.

## Servicios

| Servicio | Rol |
|----------|-----|
| `FhirSchedulingInboundPullService` | Orquesta búsqueda + cursor |
| `TurnoInboundSyncService` | Upsert espejo local |
| `FhirAppointmentOutboundSyncService` | PUT status a NIS |
| `TurnoFhirOutboundNotifier` | Hook fail-soft post-cambio de estado |

## Hooks outbound

Se invoca `TurnoFhirOutboundNotifier::afterEstadoChanged()` desde:

- `TurnoLifecycleService::cancelar`
- `TurnoResolucionService` (`EN_RESOLUCION`, vuelta a `PENDIENTE` tras reubicación)
- `BulkCancelDayService`
- `Turno::NoSePresento`, `Turno::cambiarCampoAtendido`

Errores de red hacia NIS se registran en log (`fhir-scheduling-outbound`); no revierten el cambio local.

## Consola

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

`enabled` controla pull; `outbound.enabled` controla push (requiere también `enabled`).

Ver [04-operacion.md](./04-operacion.md) para cron y troubleshooting.
