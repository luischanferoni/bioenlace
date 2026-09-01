# Fase 03 — Orquestación `clara`

## Objetivo

`clara` → flow (1 intent) o interactive (N intents).

## Tareas

- [ ] Handler `ClaraCatalogacionHandler` (o equivalente en orchestrator).
- [ ] Filtrar `intent_ids` contra catálogo + permisos.
- [ ] 1 intent: `IntentEngine::buildSingleActionResponse` / `SubIntentEngine` → `AssistantEnvelope::flowFromMotor`.
- [ ] N intents: `respuesta_al_usuario` + botones `{ label, intent_id }` → `AssistantEnvelope::interactive`.
- [ ] 0 intents: log + envelope solo texto (sin botones).
- [ ] Info/list y flows YAML: verificar happy path sin 2ª IA.
- [ ] Desambiguación del motor (`remediation`) antes de forzar flow si el motor lo exige.

## Criterio de salida

Tests: 1 intent flow, 2 intents interactive, 0 intents solo texto; envelope sin `kind: message` nuevo.
