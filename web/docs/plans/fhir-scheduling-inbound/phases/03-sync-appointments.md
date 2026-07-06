# Fase 3 — Sync Appointments → turnos

## Alcance

- Conector pull HAPI (`Appointment?_lastUpdated=…`).
- Mapper: `external_appointment_id`, `fhir_status`, `appointment_type`, refs Schedule.
- Resolver PES vía fase 2; `id_persona` opcional en paciente desconocido.
- Actualización estados salientes (`booked`, `cancelled`, `fulfilled`, …).

## Columnas turnos (existentes / a agregar)

- `fhir_status`, `appointment_type` (migración `m260520_100003`).
- Pendiente: `external_appointment_id`, `source_system`, `pes_resolution_trust`.

## Jobs

- Cron incremental + reconciliación diaria de links `stale`.
