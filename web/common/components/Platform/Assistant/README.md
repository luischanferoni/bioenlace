# Platform Assistant

Orquestación del chat: preprocess → router → canales.

## Flujo

1. **Preprocess** (`ChatPreprocessService`): `user_goal`, `normalized_text`, `context_areas`, extracciones.
2. **Router** (`ChatRouter`): despacha por `user_goal`.
3. **Hilos** (`AssistantThreadStateService`): `thread_tag` desde `assistant/routing/thread-state.yaml`.
4. **Canal** (`user_goal` preprocess): `guide`, `operational`, `ambiguous`, `in_flow_question`. Predicados de dominio: `ChatChannelPolicy`. Prompts: `assistant/prompts/`. Booking CTA: `assistant/routing/booking-offer.yaml`.

## Canales

| `user_goal` | Handler |
|-------------|---------|
| `guide` | `GuideChannel` |
| `operational` / `in_flow_question` | `OperationalChannel` |
| `ambiguous` | `AmbiguousChannel` |

Contexto HIS: `AssistantContextAssemblyService` → bloque `context:his` en prompt guide.

## Otros entrypoints

- Captura clínica: `clinical/EncounterController` → `Clinical/Assistant/ClinicalEncounterEntry`
- Smoke QA consultas paciente (CLI): `php yii qa/asistente-consultas` → `Qa/AsistenteConsultasQaService` + `common/data/qa/asistente-consultas.yaml`
