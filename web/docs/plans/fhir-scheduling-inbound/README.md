# Plan — Agendamiento FHIR entrante (HAPI → Bioenlace)

| Campo | Valor |
|-------|--------|
| Slug | `fhir-scheduling-inbound` |
| Estado | Fase 1 en curso |
| Dueño | Integraciones / scheduling |

## Índice

| Doc | Contenido |
|-----|-----------|
| [overview.md](./overview.md) | Alcance, actores, qué entra y qué no |
| [design.md](./design.md) | Capas, confianza PES, fail-closed |
| [phases/00-marco.md](./phases/00-marco.md) | Contrato coordinado con HAPI (identifiers nacionales) |
| [phases/01-datos-confianza.md](./phases/01-datos-confianza.md) | CUIL, catálogo servicios, schedule link |
| [phases/02-resolver-pes.md](./phases/02-resolver-pes.md) | Resolver compuesto + onboarding verificado |
| [phases/03-sync-appointments.md](./phases/03-sync-appointments.md) | Espejo turnos, estados FHIR |

## Código (Fase 1)

| Área | Ubicación |
|------|-----------|
| CUIL persona | `common/models/Person/Persona.php`, `Domain/Person/Service/PersonCuilService.php` |
| Alta PES + CUIL | `ProfesionalEfectorServicioAltaService`, intent `profesional-efector-servicio.crear-flow` |
| Catálogo servicio FHIR | `integration_fhir_service_code`, `FhirHealthcareServiceCodeCatalog` |
| Vínculo Schedule→PES | `integration_schedule_link`, `FhirSchedulePesResolver` |
| Integraciones | `common/components/Domain/Integrations/Scheduling/` |

## Relacionado

- [decisions/fhir-clinical.md](../../decisions/fhir-clinical.md)
- [plans/interoperabilidad-historia-clinica/](../interoperabilidad-historia-clinica/README.md) (patrón conector + mapper)
