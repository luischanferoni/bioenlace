# Fase 02 — Índice único de discovery

## Objetivo

Un matcher que, dados `tags` (+ texto), devuelve hits `intent` y `article` con la misma forma, y el ensamblador de Guide los adjunta (`intent_semantics`, `article_block`).

## Hecho

- `Catalog/DiscoveryIndex` + `DiscoveryResult`: intents vía `StateTagIndex`; artículos vía `InfoContentResolverService::rankByTagsAndText`.
- `GuidePromptAssembler::buildForIncomplete`: `intent_semantics` solo desde discovery (sin fallback smart-catalog); si falta `article_block`, lo completa desde discovery.
- `CatalogCtaResolver`: prioriza intent ids del discovery antes del catálogo legacy.
- Test: `DiscoveryIndexTest`.

## Criterio de hecho

- [x] Guide incompletas arma adjuntos desde el índice unificado.
- [x] Tests de match intent por tags.
- [ ] Test artículo por tags (requiere BD/fixtures; diferido o smoke manual).

## Siguiente

Fase 03: retirar `direct-doors` / aspectos-puerta.
