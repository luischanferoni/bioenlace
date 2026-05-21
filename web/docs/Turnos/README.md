# Turnos

Índice del dominio **agenda y turnos** (reserva, estados, autogestión, notificaciones).

| Documento | Contenido |
|-----------|-----------|
| [overview.md](./overview.md) | Qué es, objetivo, actores |
| [design.md](./design.md) | Por qué está armado así (API, PES, agenda versionada, oferta en dos pasos) |

## Flujos

| Flujo | Archivo |
|-------|---------|
| Reserva autogestión (asistente / app) | [flows/intents-turnos.md](./flows/intents-turnos.md) (intent `turnos.crear-como-paciente`) |
| Agenda, intervalo, `slot_id`, conflictos | [flows/agenda-intervalo-y-reservas.md](./flows/agenda-intervalo-y-reservas.md) |
| Rutas API y permisos | [flows/API-nomenclatura-y-RBAC.md](./flows/API-nomenclatura-y-RBAC.md) |
| Cancelación paciente | [flows/cancelacion-paciente.md](./flows/cancelacion-paciente.md) |
| Cancelación médico/efector | [flows/cancelacion-medico.md](./flows/cancelacion-medico.md) |
| Política autogestión | [flows/politica-cancelacion-autogestion.md](./flows/politica-cancelacion-autogestion.md) |
| Reprogramación | [flows/reprogramacion-ui.md](./flows/reprogramacion-ui.md) |
| Notificaciones push | [flows/notificaciones-push.md](./flows/notificaciones-push.md) |
| Sobreturno | [flows/sobreturno.md](./flows/sobreturno.md) |
| Cancelación masiva | [flows/cancelacion-masiva.md](./flows/cancelacion-masiva.md) |
| Solicitudes entre médicos | [flows/solicitudes-medicos.md](./flows/solicitudes-medicos.md) |

## Anclas en código (sin abrir los flujos)

| Rol | Ubicación |
|-----|-----------|
| API v1 | `TurnosController`, `ProfesionalAgendaController` |
| Servicios | `common/components/Scheduling/Service/*` |
| Modelo | `common/models/Turno`, `Scheduling/Turno` |
| Intents YAML | `Assistant/SubIntentEngine/schemas/intents/turnos.*.yaml` |
