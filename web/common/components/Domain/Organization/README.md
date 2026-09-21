# Organization (BC)

Módulo-primero (fase 02 del plan DDD empaquetado transversal).

| Módulo | Capacidad |
|--------|-----------|
| `Efector/` | Centro, billing, entitlement, seeds demo |
| `Servicio/` | Oferta institucional del centro (`id_servicio`) |
| `Pes/` | ProfesionalEfectorServicio, horarios, agenda PES, listados |
| `SesionOperativa/` | set-session / contexto operativo |
| `Assistant/` · `DataAccess/` | Plugins del BC |

Cada módulo: `Application/{Service,Authorization,Presentation,Flows,Seed…}` · `Domain/` · `Infrastructure/`.
