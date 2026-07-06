# Fase 0 — Contrato con HAPI NIS

Servidor acordado: **[HAPI FHIR NIS MSAL](https://nis.msalsgo.gob.ar/fhir)** (R4, HAPI 8.x).

| Tema | Convención |
|------|------------|
| Base URL | `https://nis.msalsgo.gob.ar/fhir` (`params.fhirSchedulingInbound.connectors.msal-nis`) |
| Efector | `Location.identifier` / SISA (system a cerrar con NIS) |
| Profesional | `Practitioner.identifier` CUIL `http://www.afip.gob.ar/cuil` |
| Paciente | `Patient.identifier` DNI/CUIL cuando exista en Bioenlace |
| Servicio | `HealthcareService.specialty` → catálogo `integration_fhir_service_code` |
| Agenda | `Appointment.slot` → `Slot` → `Schedule/{id}` |
| Pull | `GET Appointment?_lastUpdated=gt{instant}&_count=N` |

OAuth: opcional vía `tokenUrl` / `clientId` en `params-local.php` cuando NIS lo exija.

## Entregable

Fixtures Bundle de ejemplo + golden tests en `common/tests/unit/integrations/scheduling/`.
