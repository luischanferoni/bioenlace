# Fase 02 — Prompts y metadata YAML

## Objetivo

Centralizar prompts por canal y ordenar carpetas bajo `assistant/`.

## Checklist

- [ ] Crear `assistant/prompts/` (`preprocess`, `conversational_clinical`, `informational_conversational`, `ambiguous_conversational`).
- [ ] Mover copy UI a `assistant/copy/channel-copy.yaml`.
- [ ] Mover routing (`intent-families`, `hint-resolution`, prioridad booking-offer) a `assistant/routing/`.
- [ ] Loaders PHP genéricos (un config por archivo); sin hardcode de textos de canal en orquestadores.
- [ ] Prompt informational: “respondé solo con la fuente inyectada; si falta, decilo”.
- [ ] Prompt clinical: reglas actuales + perímetro paciente; oferta CTA alineada.
- [ ] Ambiguous: lista de preguntas/botones en YAML (ids estables → next_channel).

## Criterio de salida

Cambiar copy/prompt de un canal = editar YAML + cache bust; no tocar `ChatRouter` por un string.
