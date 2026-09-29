# Routing

Post-preprocess unificado (`ChatRouter`) — discovery unificado.

## Hints IA

`guide` | `fuera_his` | `sin_pedido`

## Flujo

1. Preprocess → hint + tags
2. `DiscoveryIndex` → intents (`meta.tags`) + artículos BD
3. Áreas derivadas de intents → plan declarativo (aspectos HIS)
4. Handlers:

| `routing_result` | Handler |
|------------------|---------|
| `clara` / `incompletas` | `IncompleteRoutingHandler` → Guide |
| `dudosa` | `DudosaRoutingHandler` |
| `fuera_de_his` | `FueraDeHisHandler` |

Copy de límites de canal: `Application/Routing/channel-limits.yaml`.
No hay catálogo de puertas (`direct-doors` eliminado).
