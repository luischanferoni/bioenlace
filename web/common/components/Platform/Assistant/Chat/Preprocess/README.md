# Preprocess

1ª IA (`routing_hint` + tags + extracciones). Prompt: `assistant/preprocess/prompt.yaml`.

`user_goal` (`guide` / `operational` / `ambiguous` / `in_flow_question`) se **deriva** del hint (+ tag `in_flow_question`) para hilo y callers de canal — no es el eje de la IA. Goals desconocidos → `ambiguous` (`ChatPreprocessService::canonicalizeGoal`).

Predicados de dominio: `ChatChannelPolicy`. Catálogo hints: `assistant/catalog/preprocess-routing-hints.yaml` + `PreprocessRoutingHintCatalog`.
