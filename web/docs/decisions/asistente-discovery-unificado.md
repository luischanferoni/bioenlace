# Asistente: discovery unificado (tags → Guide)

## Contexto

El “catálogo inteligente” (`direct-doors` / smart-catalog) duplicaba la idea de discovery ya presente en `meta.tags` de los flows y en keywords de artículos BD. Además, un match 100 % a artículo o template salteaba la 2ª IA, en tensión con el modelo “todo pedido HIS se orienta en Guide”.

## Decisión

```text
preprocess → hint ∈ { guide | fuera_his | sin_pedido }
                ↓
         si guide:
           tags (+ texto) → DiscoveryIndex
             • intents: meta.tags en YAML Domain
             • artículos: keywords/tags en BD
                ↓
           hits → adjuntos Guide (intent_semantics, article_block, HIS)
                ↓
           2ª IA siempre (nunca DirectMatch)
```

1. **Hints IA** — solo tres valores en `preprocess-routing-hints.yaml` (loader PHP remapea ids legacy).
2. **Discovery** — `Catalog/DiscoveryIndex`: misma forma de hit para intent y artículo; no elige canal.
3. **Canal** — lo decide el hint (y tag `fuera_his`); paths internos `clara` / `incompletas` / `dudosa` / `fuera_de_his` son vocabulario de handlers en transición.
4. **Aspectos HIS** — loaders por área derivada del intent; **no** puertas NL en Platform.
5. **Sin `direct-doors`** — eliminado; el índice de producto son tags de intents + artículos BD.

## Alternativas descartadas

- Mantener smart-catalog como índice paralelo de aspectos/artículos/fuera HIS.
- Artículo o template sin 2ª IA (DirectMatch).
- Pedir `context_areas` a la 1ª IA.

## Consecuencias

- Intents Domain deben ser ricos en `meta.tags` (y en lo que Guide necesita saber del recorrido).
- Artículos: keywords en BD alineados a la misma noción de tag.
- Eliminado: `SmartCatalogRegistry` / `SmartCatalogMatchService` / `DirectMatchHandler` / `direct-doors.yaml`.
- Docs: [producto/asistente-y-chat.md](../producto/asistente-y-chat.md), [contenido-informativo.md](../producto/contenido-informativo.md), [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md).
- Reemplaza: [asistente-catalogo-inteligente.md](./asistente-catalogo-inteligente.md) (obsoleto).

Relacionado: [asistente-contexto-his-areas-aspectos.md](./asistente-contexto-his-areas-aspectos.md), [asistente-canal-guide.md](./asistente-canal-guide.md).
