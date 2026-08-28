# Fase 3 — Ensamblaje y 2ª IA

## Objetivo

Adjuntar volcado HIS a prompts de canales que usan segunda IA.

## Tareas

- [ ] `AssistantContextFormatter` (JSON + delimitadores + truncado).
- [ ] `AssistantContextAssemblyService` (plan → loaders → `promptSection()`).
- [ ] Request-scoped cache de loaders.
- [ ] Integrar `InfoContentAssistantService::buildArticlePrompt`.
- [ ] Integrar `ConversationalChannel::buildPrompt` (coexistir con HC extracto inicialmente).
- [ ] Actualizar `informational.yaml` / `clinical.yaml`: reglas sobre bloque `context:his` y `limitations`.
- [ ] Debug: `context_applied` en envelope cuando flag activo.
- [ ] Telemetría: opcional log chars por aspecto.

## Criterio de salida

Pregunta informational con área `appointments` recibe volcado en prompt; saludo no ejecuta loaders; QA caso tardanza documentado.
