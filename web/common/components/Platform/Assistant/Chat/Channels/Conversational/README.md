# Canal conversational_clinical

Respuesta libre con IA (empatía, orientación) y, cuando aplica, botón **Solicitar Atención**.

## Cuándo hay CTA

1. Síntoma en el mensaje actual, o en el historial del **hilo clinical** activo.
2. Certeza del hilo (`thread-state.yaml`) por encima del umbral — salvo saludo puro sin síntoma.
3. Tras un síntoma propio, aunque diga «estoy bien» → sí CTA.
4. Saludo solo (`hola`) sin síntoma en el hilo → sin CTA.

Prioridad de intents y `capability_labels`: `assistant/routing/booking-offer.yaml`.
Prompt / fragments: `assistant/prompts/conversational_clinical.yaml`.

Del YAML del intent de oferta se leen `intent_semantics.summary` y `capabilities` (ficha corta para el prompt). Keywords de descubrimiento van en `keywords` del intent.
