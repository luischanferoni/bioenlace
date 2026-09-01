# Fase 02 — Preprocess + router

## Objetivo

Reemplazar preprocess `user_goal` por `catalogacion` y cablear router.

## Tareas

- [ ] Reescribir `prompts/preprocess.yaml`: rol HIS, `{funcionalidades}`, schema JSON, reglas de las 4 catalogaciones.
- [ ] `ChatPreprocessService::normalizeResult()` valida `catalogacion` y campos condicionales.
- [ ] Sesión: adjuntar historial + estado flow (`intent_id`, `subintent_id`, draft) al prompt preprocess.
- [ ] `ChatPreprocessContext` almacena nuevo shape.
- [ ] `ChatRouter`: ramificar por `catalogacion` (no por `user_goal`).
- [ ] Mantener `action_id` en body como atajo (sin re-preprocess completo si se define así en ADR).

## Criterio de salida

Tests unitarios de normalización JSON; router despacha a handlers stub de cada catalogación.
