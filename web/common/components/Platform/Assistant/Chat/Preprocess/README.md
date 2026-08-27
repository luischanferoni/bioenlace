# Preprocess

Clasificación IA de cada mensaje raíz: `user_goal`, texto normalizado, extracciones.

Goals canónicos: `operational`, `clinical`, `informational`, `ambiguous`, `in_flow_question`, `meta`.

Alias legacy (`conversational`, `conversational_clinical`, `informational`, `informational_conversational`, `unclear`, `ambiguous_conversational`) en `ChatPreprocessService::canonicalizeGoal`.

Prompt: `assistant/prompts/preprocess.yaml`. Predicados PHP: `ChatChannelPolicy`.
