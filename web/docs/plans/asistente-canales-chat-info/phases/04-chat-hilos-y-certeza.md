# Fase 04 — Chat: hilos, desvío y certeza

## Objetivo

Módulo de conversación que no mezcle dominios y sepa cuándo ya entiende la necesidad.

## Checklist

- [ ] Persistir tag de dominio/hilo en `asistente_interaccion` (o tabla satélite).
- [ ] Ventana de historial filtrada por hilo activo para prompts clinical/info.
- [ ] Detector de desvío (mensaje actual vs tag del hilo) → ambiguous o nuevo hilo.
- [ ] Estado de certeza por hilo: hipótesis + `confidence` (knobs en metadata).
- [ ] Clinical/info: preguntas **libres** mientras confidence baja; al umbral → CTA/flow.
- [ ] Ambiguous: preferir preguntas **predefinidas** (fase 02) para encauzar rápido.
- [ ] No adjuntar `content` de botón clínico de otro hilo.

## Criterio de salida

Tras un síntoma, una pregunta de representación no reusa el pinchazo ni el CTA clínico; el asistente puede preguntar hasta “ya sé qué querés” y recién ahí ofrecer el intent.
