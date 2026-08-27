# Enruta por `user_goal` tras preprocess (+ hilo / desvío).

| `user_goal` | Handler |
|-------------|---------|
| `clinical` | `ConversationalChannel` |
| `informational` / `meta` | `InformationalChannel` |
| `ambiguous` | `AmbiguousChannel` (botones fijos → `assistant.channel.*`) |
| `operational` / `in_flow_question` | `OperationalChannel` |

Alias legacy (`conversational_clinical`, `informational_conversational`, …) se canonicalizan en `ChatPreprocessService::canonicalizeGoal`.
