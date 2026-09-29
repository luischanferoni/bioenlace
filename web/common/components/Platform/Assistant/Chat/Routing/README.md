# Routing

Post-preprocess unificado (`ChatRouter`).

## Hints IA (canónicos)

`guide` | `fuera_his` | `sin_pedido` — ver plan `docs/plans/asistente-discovery-unificado/`.

## `routing_result` → handler (transición)

| `routing_result` | Handler |
|------------------|---------|
| `clara` (intent / artículo / template) | `IncompleteRoutingHandler` → Guide (2ª IA). Fallback botón: `ClaraRoutingHandler` |
| `dudosa` | `DudosaRoutingHandler` |
| `fuera_de_his` | `FueraDeHisHandler` |
| `incompletas` | `IncompleteRoutingHandler` (+ opcional `PlannerRoutingStep`) |

`DirectMatchHandler` está deprecado (ya no se usa en el hot path).

Fallback sin match handler: `LegacyRoutingFallback`.

El `user_goal` del hilo (`guide` / `ambiguous`) se deriva en `PreprocessRoutingHintCatalog`.

Plan activo: `web/docs/plans/asistente-discovery-unificado/`.
ADR vigente hasta cierre: `web/docs/decisions/asistente-catalogo-inteligente.md`.
