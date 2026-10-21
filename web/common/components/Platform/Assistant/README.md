# Platform Assistant

Orquestación del chat: preprocess → router → guía (2ª IA) o canal de respuesta.

## Flujo

1. **Preprocess** (`ChatPreprocessService`): `routing_hint`, `normalized_text`, tags, extracciones; deriva `user_goal` para hilo/canal. Las `context_areas` las calcula el match (carpeta del intent), no la 1ª IA.
2. **Router** (`ChatRouter`): evalúa catálogo inteligente → `routing_result` → handlers.
3. **Hilos** (`AssistantThreadStateService`): `thread_tag` desde goal (mapa PHP) + knobs en `assistant/routing/thread-state.yaml`.
4. **Destino**: 2ª IA Guide (`Application/Guide/prompt.yaml`) cuando hay pedido HIS; canal ambiguous (`Channels/Ambiguous/`); operativo staff. Hilo: `user_goal` derivado. Predicados: `ChatChannelPolicy`. Booking CTA: `Application/Routing/booking-offer.yaml`.

## Routing result (eje PHP)

| `routing_result` | Destino típico |
|------------------|----------------|
| `clara` | Guía (2ª IA) y botón del intent. Artículo o plantilla siguen en la misma respuesta, sin guía |
| `incompletas` | La misma guía + plan HIS |
| `dudosa` | Encauzamiento ambiguous |
| `fuera_de_his` | Texto fijo |

## Otros entrypoints

- Captura clínica: `clinical/EncounterController` → `Capture/Application (EncounterController)`
- Smoke QA consultas paciente (CLI): `php yii qa/asistente-consultas` → `Qa/PatientAssistantQaService` + `common/data/qa/asistente-consultas.yaml`
