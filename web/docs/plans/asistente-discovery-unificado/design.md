# Design — Discovery unificado

## Capas

| Capa | Responsabilidad |
|------|-----------------|
| Preprocess | `normalized_text`, `necesidades_usuario`, `routing_hint`, `tags`, `extractions` |
| Hint → canal | Solo tres ramas: Guide / fuera HIS / sin pedido (copy fijo) |
| Discovery | Match determinista tags (+ texto) → lista de hits |
| Guide | 2ª IA con adjuntos; CTAs como botones cuando corresponda |

## Hints (source of truth YAML + loader PHP)

| Id | Efecto |
|----|--------|
| `guide` | Canal Guide + discovery + adjuntos |
| `fuera_his` | Respuesta fuera del ámbito (sin Guide) |
| `sin_pedido` | Respuesta predefinida (saludo / sin necesidad) |

Sin `pedido_claro`, `pedido_claro_multiple`, ni aliases que remapeen paths PHP a hints.

## Discovery (fase 02+)

Hit lógico común:

- `kind`: `intent` \| `article`
- `id` / `topic`
- `tags` (y opcionalmente phrases en el YAML del intent)

Fuentes:

- Intents: `states.*.meta.tags` (YAML Domain).
- Artículos: keywords/tags en `info_content_article` (BD), misma noción de tag.

No hay `kind: aspect` en discovery. El contexto HIS que haga falta lo declara el intent rico (fase 03) o el plan de carga ligado al intent matcheado.

## Qué se retira

- `direct-doors.yaml` / smart-catalog como índice de puertas.
- `DirectMatchHandler` (artículo/template sin 2ª IA).
- Paths PHP `clara` / `incompletas` como eje de producto (quedan solo mientras dure la migración, mapeados a guide).
- Aspectos como entrada de catálogo (`tool_type: aspect`).

## Qué permanece

- Loaders de datos HIS en Domain/Platform como **infra de carga**, no como puerta NL.
- `StateTagIndex` evoluciona o se fusiona en el índice único.
- Artículos en BD con scope efector → provincia → producto.

## Anti-patrones

- Caso particular en Platform YAML (“si dice medium → fuera_his”).
- Re-interpretar el hint en PHP hacia otro canal salvo bug/fallback técnico.
- Duplicar listas de tags en prompt + YAML + BD sin un loader.
