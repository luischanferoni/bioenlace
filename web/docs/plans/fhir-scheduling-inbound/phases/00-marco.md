# Fase 0 — Contrato con HAPI

Acordar con el equipo HAPI (sin `urn:bioenlace:pes`):

| Tema | Convención |
|------|------------|
| Efector | `Location.identifier` system SISA acordado, value = código SISA |
| Profesional | `Practitioner.identifier` CUIL (`http://www.afip.gob.ar/cuil` u OID acordado); DNI RENAPER como respaldo |
| Servicio | `HealthcareService.specialty` con code system estable (SNOMED u catálogo ministerial) |
| Agenda | `Appointment.slot` → `Schedule/{id}` estable |
| Estados | Mapeo Bioenlace ↔ FHIR `Appointment.status` (p. ej. `EN_RESOLUCION` → `booked`) |

## Entregable

Documento adjunto al ticket de integración + fixtures Bundle de ejemplo para tests.
