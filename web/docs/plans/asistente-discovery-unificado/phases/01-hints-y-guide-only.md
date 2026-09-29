# Fase 01 — Hints unificados + artículos siempre por Guide

## Objetivo

1. Vocabulario de hint: `guide` | `fuera_his` | `sin_pedido`.
2. Quitar legacy de hints (`pedido_claro*`, aliases a paths PHP, `user_goal` como eje).
3. Artículo / template **no** saltean la 2ª IA: van como adjunto o caen a Guide.

## Prompt / catálogo (edición humana)

Sugerencia para `Application/Catalog/preprocess-routing-hints.yaml` (aplicar a mano):

```yaml
hints:
  guide: oraciones que expresan una o más necesidades, situaciones o problemas entendibles dentro del ámbito de la salud.
  fuera_his: oraciones que expresan un tema ajeno al ámbito de la salud.
  sin_pedido: oración u oraciones que no expresan una necesidad, situación o problema entendible.
```

El agente actualiza el loader PHP y el cableado; el YAML de hints lo aplica el humano.

## Cambios de código

- `PreprocessRoutingHintCatalog`: ids nuevos; aliases temporales solo de ids viejos → nuevos; `decisionPathFromHint` colapsa a guide / fuera / sin_pedido.
- Router / handlers: hint `guide` → siempre `IncompleteRoutingHandler` (Guide). `fuera_his` / `sin_pedido` → handlers actuales de fuera / dudosa.
- `SmartCatalogRoutingHandlers`: si el match era artículo/template “clara”, **no** llamar `DirectMatchHandler`; forzar Guide con artículo en adjuntos (reusar resolución ya usada en Guide).
- Tests de catalog / routing / preprocess.

## Criterio de hecho

- Preprocess acepta solo los tres hints (más aliases de transición documentados).
- Un match de artículo del catálogo responde vía Guide, no envelope directo.
- Tests unitarios verdes en el paquete assistant afectado.

## No en esta fase

- Borrar `direct-doors.yaml`.
- Índice único completo (fase 02).
- Migrar “llego tarde” a intent/artículo (fase 03).
