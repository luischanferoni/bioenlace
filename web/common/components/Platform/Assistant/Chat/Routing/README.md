# Routing

Despacho por `user_goal` (preprocess IA) hacia canales:

| Goal | Canal |
|------|--------|
| `operational` / `in_flow_question` | `OperationalChannel` |
| `conversational_clinical` | `ConversationalChannel` |
| `informational_conversational` / `meta` | `InformationalChannel` |
| `ambiguous_conversational` | `AmbiguousChannel` (botones fijos → `assistant.channel.*`) |

Alias legacy (`conversational`, `informational`, `unclear`) → nombres canónicos en `ChatPreprocessService::canonicalizeGoal`.
