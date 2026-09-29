# Routing

Post-preprocess unificado (`ChatRouter`) — discovery unificado (plan activo).

## Hints IA

`guide` | `fuera_his` | `sin_pedido`

## Flujo

1. Preprocess → hint + tags
2. `DiscoveryIndex` → intents (`meta.tags`) + artículos BD
3. Áreas derivadas de intents → plan declarativo (aspectos HIS, no puertas NL)
4. Handlers:

| `routing_result` | Handler |
|------------------|---------|
| `clara` / `incompletas` | `IncompleteRoutingHandler` → Guide |
| `dudosa` | `DudosaRoutingHandler` |
| `fuera_de_his` | `FueraDeHisHandler` |

`direct-doors.yaml` está vacío. `SmartCatalogMatchService` / `DirectMatchHandler` son legacy.

Plan: `web/docs/plans/asistente-discovery-unificado/`.
