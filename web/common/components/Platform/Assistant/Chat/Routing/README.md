# Routing

Post-preprocess unificado (`ChatRouter`):

| `routing_result` | Handler |
|------------------|---------|
| `clara` (match 100 % artículo/template) | `DirectMatchHandler` |
| `clara` (match 100 % intent) | `ClaraRoutingHandler` |
| `dudosa` | `DudosaRoutingHandler` |
| `fuera_de_his` | `FueraDeHisHandler` |
| `incompletas` | `IncompleteRoutingHandler` (+ opcional `PlannerRoutingStep`) |

Fallback sin match handler: `LegacyRoutingFallback` (operational / dudosa / mensaje síntesis).

El alias `user_goal: guide` en hilo equivale a routing **incompletas**; no hay `GuideChannel` en raíz. `directo` es alias de preprocess hacia `clara`.

ADR: `web/docs/decisions/asistente-catalogo-inteligente.md`
