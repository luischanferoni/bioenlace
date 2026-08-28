# Fase 1 — Preprocess y `context_areas`

## Objetivo

La 1ª IA clasifica canal y **áreas HIS**; saludos devuelven lista vacía.

## Tareas

- [ ] Crear `AssistantContextHISArea` (enum + `catalogForPreprocess()` + `all()`).
- [ ] Ampliar `preprocess.yaml`: placeholder `{context_areas_catalog}`, schema JSON con `context_areas`.
- [ ] `ChatPreprocessService::stablePromptPrefix()` inyecta catálogo desde enum.
- [ ] `normalizeResult()` valida y normaliza `context_areas`.
- [ ] Ampliar `ChatPreprocessContext` con `contextAreas(): list<string>`.
- [ ] Tests unitarios: validación áreas, saludo → `[]`, mensaje citas → contiene `appointments` (mock IA o fixture JSON).

## No incluye

- Loaders ni volcado 2ª IA.
- Cambios en canales.

## Criterio de salida

Preprocess en producción devuelve `context_areas` coherente; tests green; sin regresión en goals/canales.
