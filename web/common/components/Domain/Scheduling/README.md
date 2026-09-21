# Scheduling (BC)

Módulo-primero (fase 03 del plan DDD empaquetado transversal).

| Módulo | Capacidad |
|--------|-----------|
| `Agenda/` | Turnos, async, triage, agents, FHIR inbound/outbound |
| `BehaviorProfile/` | Perfil de comportamiento / eventos canónicos |
| `Quirofano/` | Cirugías / salas |
| `Home/` | Sections + listados día staff |
| `Assistant/` | Plugin del BC |

Cada módulo: `Application/{Service,Agents,Authorization,Presentation,Flows…}` · `Domain/` · `Infrastructure/`.
