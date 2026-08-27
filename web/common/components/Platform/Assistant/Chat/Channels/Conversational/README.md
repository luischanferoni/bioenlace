# Canal clinical

Charla breve sobre salud/malestar sin trámite nombrado + CTA Solicitar Atención (2.ª IA).

Cuándo hay CTA:

1. Síntoma en el mensaje actual, o en el historial del **hilo clinical** activo.
2. Certeza del hilo (`thread-state.yaml`) por encima del umbral — salvo saludo puro sin síntoma.
3. Tras un síntoma propio, aunque diga «estoy bien» → sí CTA.

Prioridad de intents y `capability_labels`: `assistant/routing/booking-offer.yaml`.
Prompt / fragments: `assistant/prompts/clinical.yaml`.
