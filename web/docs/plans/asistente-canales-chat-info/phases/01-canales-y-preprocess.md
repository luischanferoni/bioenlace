# Fase 01 — Canales y preprocess

## Objetivo

Renombrar/estabilizar goals del preprocess y el router; `ambiguous_conversational` con encauzamiento por botones fijos; dejar de mezclar clinical e informational.

## Checklist

- [x] Ampliar `ChatPreprocessService::GOALS` (o mapear alias) a nombres nuevos.
- [x] `ChatRouter`: ramas por canal nuevo; `ambiguous` → envelope con preguntas predefinidas (metadata).
- [x] Prompt preprocess solo en YAML (tras fase 02) o seguir en PHP hasta migrar; reglas afirmativas (ya iniciado).
- [x] `InformationalChannel`: **no** caer a `ConversationalChannel` si no hay artículo; mensaje corto o ambiguous.
- [x] `ConversationalChannel` (clinical): **quitar** `tryResolveFromText`.
- [x] Oferta Solicitar Atención: solo si el mensaje **actual** es clínico (no arrastrar historial global) — o historial del hilo clinical activo (fase 04).
- [x] Tests de router / policy / ambiguous sin depender de IA real (fixtures de `user_goal`).

## Criterio de salida

Mensajes de ayuda de producto no reciben botón Solicitar Atención por un síntoma viejo en BD; `unclear`/ambiguous ofrece encauzamiento; fallo de preprocess IA → error, no heurística.
