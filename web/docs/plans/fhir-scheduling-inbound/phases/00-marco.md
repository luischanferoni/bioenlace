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
| Pull | `GET Appointment?_lastUpdated=gt{instant}&_count=N&_sort=-_lastUpdated` |
| Push estado | `GET Appointment/{id}` + `PUT Appointment/{id}` con `status` actualizado |

OAuth: opcional vía `tokenUrl` / `clientId` / `clientSecret` en `params-local.php` cuando NIS lo exija.

## Búsquedas útiles (exploración manual)

```http
GET /fhir/Schedule?_count=10
GET /fhir/Appointment?_count=10
GET /fhir/Schedule/{id}?_include=Schedule:actor
```

Al momento de implementar el pipeline, las búsquedas abiertas en NIS devolvían **0** `Schedule` / `Appointment` — el conector responde 200; hace falta datos reales o filtros por efector acordados con MSAL.

## Entregables

| Ítem | Estado |
|------|--------|
| Conector `MsalNisFhirSchedulingConnector` | Hecho |
| Fixtures Bundle + golden tests | Pendiente (requiere Bundle de ejemplo de NIS) |

Ruta prevista para fixtures: `common/tests/fixtures/fhir/scheduling/`.
