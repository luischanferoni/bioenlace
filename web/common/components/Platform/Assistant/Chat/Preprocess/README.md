# Preprocess

IA de preproceso: `normalized_text`, `user_goal`, `action_text`, `extractions`.

El `user_goal` lo decide la IA (sin piso PHP ni fallback heurístico). Si la IA falla, el router responde error y no clasifica.

Goals canónicos: `operational`, `conversational_clinical`, `informational_conversational`, `ambiguous_conversational`, `in_flow_question`, `meta`. Alias legacy (`conversational`, `informational`, `unclear`) en `ChatPreprocessService::canonicalizeGoal`.

Predicados de dominio (síntoma, staff data-access, menú): `ChatChannelPolicy`.
