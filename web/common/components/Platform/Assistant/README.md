# Platform Assistant

Orquestación del chat: preprocess → router → canales.

## Flujo

1. **Preprocess** (`ChatPreprocessService`): `routing_hint`, `normalized_text`, `context_areas`, tags, extracciones; deriva `user_goal` para hilo/canal.
2. **Router** (`ChatRouter`): evalúa catálogo inteligente → `routing_result` → handlers.
3. **Hilos** (`AssistantThreadStateService`): `thread_tag` desde goal (mapa PHP) + knobs en `assistant/routing/thread-state.yaml`.
4. **Canal / hilo** (`user_goal` derivado): `guide`, `operational`, `ambiguous`, `in_flow_question`. Predicados: `ChatChannelPolicy`. Prompts/UX: `assistant/channels/{Name}/`. Booking CTA: `assistant/routing/booking-offer.yaml`.

## Routing result (eje PHP)

| `routing_result` | Destino típico |
|------------------|----------------|
| `clara` | Intent / artículo / template (1 IA) |
| `incompletas` | Guide + plan HIS (2ª IA) |
| `dudosa` | Encauzamiento ambiguous |
| `fuera_de_his` | Texto fijo |

## Otros entrypoints

- Captura clínica: `clinical/EncounterController` → `Clinical/Assistant/ClinicalEncounterEntry`
- Smoke QA consultas paciente (CLI): `php yii qa/asistente-consultas` → `Qa/AsistenteConsultasQaService` + `common/data/qa/asistente-consultas.yaml`
