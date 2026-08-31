# Canal guide del asistente

## Contexto

Existían dos canales de 2ª IA (`clinical`, `informational`) con hilos distintos, prompts duplicados y desvíos frágiles. El paciente percibe una sola guía en el HIS.

## Decisión

- **Un solo `user_goal: guide`** en preprocess (sin aliases legacy).
- **`GuideChannel`** + `guide.yaml` reemplazan `ConversationalChannel` e `InformationalChannel`.
- Telemetría IA: `asistente-guide`.
- El volcado HIS (`context:his`) y áreas/aspectos siguen como en [asistente-contexto-his-areas-aspectos.md](./asistente-contexto-his-areas-aspectos.md).
- Hilo de conversación: `thread_tag: guide` (reemplaza `clinical` / `product_help`).

Goals canónicos preprocess: `guide`, `operational`, `ambiguous`, `in_flow_question`.

## Consecuencias

- `GuidePromptAssembler`: HC (`PROFILE_GUIDE`), `context:his`, `context:intent_semantics`, historial por foco persistido (`guide_focus`).
- QA y docs: referir canal **guide**, no clinical/informational.
