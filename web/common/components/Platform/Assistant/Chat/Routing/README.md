# Routing

Post-preprocess unificado (`ChatRouter`):

| `routing_result` | Handler |
|------------------|---------|
| `directo` | `DirectMatchHandler` |
| `clara` | `ClaraRoutingHandler` |
| `dudosa` | `DudosaRoutingHandler` |
| `fuera_de_his` | `FueraDeHisHandler` |
| `incompletas` | `IncompleteRoutingHandler` (+ opcional `PlannerRoutingStep`) |

Fallback sin match handler: `LegacyRoutingFallback` (operational / dudosa / mensaje síntesis).

El alias `user_goal: guide` en hilo equivale a routing **incompletas**; no hay `GuideChannel` en raíz.

ADR: `web/docs/decisions/asistente-catalogo-inteligente.md`
