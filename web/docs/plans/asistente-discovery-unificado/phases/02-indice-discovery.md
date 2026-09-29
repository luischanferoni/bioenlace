# Fase 02 — Índice único de discovery

## Objetivo

Un matcher que, dados `tags` (+ texto), devuelve hits `intent` y `article` con la misma forma, y el ensamblador de Guide los adjunta (`intent_semantics`, `article_block`).

## Alcance

- Extender o reemplazar `StateTagIndex` como fachada de discovery.
- Artículos: discovery por keywords/tags de BD sin pasar por smart-catalog.
- Intents: seguir leyendo `meta.tags` de YAML Domain.
- Retirar dependencia de score smart-catalog para armar adjuntos de Guide.

## Criterio de hecho

- Guide incompletas arma adjuntos solo desde el índice unificado.
- Tests de match intent + artículo por tags.
