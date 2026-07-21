# Canal conversacional

Respuesta libre con IA (empatía, orientación) y, cuando aplica, botón a un intent del catálogo.

## Oferta alineada al botón

1. Si `booking_button.when_rule` matchea el mensaje, se resuelve un intent (`intent_priority` / prefijo) vía `UiActionCatalog` (RBAC).
2. Del YAML del intent se leen `intent_semantics.summary` y `intent_semantics.capabilities`.
3. Ese bloque se inyecta en el prompt (`formatOfferForPrompt`) y el mismo intent se emite como botón en el envelope.
4. Las etiquetas humanas de capabilities viven en `intent-classification-rules.yaml` → `conversational_channel.capability_labels` (no hardcode en PHP).

El modelo solo debe prometer lo declarado en esa oferta.
