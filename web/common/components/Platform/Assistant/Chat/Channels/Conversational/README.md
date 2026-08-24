# Canal conversacional

Respuesta libre con IA (empatía, orientación) y, cuando aplica, botón a un intent del catálogo.

## Oferta alineada al botón

1. Si `ChatChannelPolicy` detecta síntoma (mensaje o historial), se ofrece un intent de `booking_offer_intent_priority` vía `UiActionCatalog` (RBAC).
2. Del YAML del intent se leen `intent_semantics.summary` y `intent_semantics.capabilities` (solo esas claves; el resto va en `keywords` o se omite).
3. Ese bloque se inyecta en el prompt (`formatOfferForPrompt`) y el mismo intent se emite como botón en el envelope.
4. Etiquetas humanas de capabilities: `conversational-channel.yaml` → `capability_labels`.
5. Copy del prompt: `stable_prompt` + `prompt_fragments` en ese YAML (`ChatConversationalConfig::promptFragment`); PHP solo ensambla bloques variables.

El modelo solo debe prometer lo declarado en esa oferta.
