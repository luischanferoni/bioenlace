# Integraciones — Agendamiento FHIR entrante

Consumo de `Schedule` / `Appointment` desde HAPI (u otro servidor FHIR).

| Componente | Rol |
|------------|-----|
| `FhirHealthcareServiceCodeCatalog` | Código HealthcareService → `id_servicio` |
| `FhirSchedulePesResolver` | Schedule → PES (catálogo + compuesto fail-closed) |
| `ScheduleActorSet` | DTO de actores normalizados |

Plan: `web/docs/plans/fhir-scheduling-inbound/`
