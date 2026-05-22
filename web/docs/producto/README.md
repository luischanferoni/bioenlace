# Producto (apps y registro)

Experiencia de **paciente y médico** en apps móvil, SPA y alta de identidad.

| Documento | Contenido |
|-----------|-----------|
| [overview.md](./overview.md) | Qué cubre este dominio |
| [design.md](./design.md) | Por qué API de registro vs alta manual web |

## Flujos

| Flujo | Archivo |
|-------|---------|
| Capacidades transversales (chat, IA, medios, video) | [flows/capacidades-paciente-medico.md](./flows/capacidades-paciente-medico.md) |
| Registro paciente/médico (Verifik, MPI, REFEPS) | [flows/registro-paciente.md](./flows/registro-paciente.md) |

## Relacionado

- [Turnos](../Turnos/README.md) — reserva y autogestión
- [asistente](../asistente/README.md) — intents y chat
- [costos](../costos/README.md) — estimación IA/medios
- Care plans en app paciente: API `GET /api/v1/clinical/care-plans/active` (ver [decisions/fhir-clinical.md](../decisions/fhir-clinical.md))
