# Routing

Post-preprocess unificado (`ChatRouter`):

| `routing_result` | Handler |
|------------------|---------|
| `clara` (match 100 % artículo/template) | `DirectMatchHandler` |
| `clara` (match 100 % intent) | `ClaraRoutingHandler` |
| `dudosa` | `DudosaRoutingHandler` |
| `fuera_de_his` | `FueraDeHisHandler` |
| `incompletas` | `IncompleteRoutingHandler` (+ opcional `PlannerRoutingStep`) |

Fallback sin match handler: `LegacyRoutingFallback` (operational / dudosa / mensaje guide).

Eje de la 1ª IA / catálogo: `routing_hint` → `routing_result` (PHP). El `user_goal` del hilo (`guide` / `operational` / `ambiguous`) se deriva en `PreprocessRoutingHintCatalog` para thread-state y callers de canal; `guide` en hilo ≈ camino **incompletas**.

ADR: `web/docs/decisions/asistente-catalogo-inteligente.md`
