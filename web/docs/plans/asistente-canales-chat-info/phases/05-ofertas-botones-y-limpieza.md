# Fase 05 — Ofertas, botones y limpieza

## Objetivo

Cerrar deuda de oferta de botones, perímetro y docs estables.

## Checklist

- [ ] Clinical: siempre botón Solicitar Atención cuando el canal es clinical (saludo-only: ¿botón o no? — default: sin síntoma en hilo, saludo sin CTA clínico).
- [ ] Casos borde documentados en QA (`asistente-consultas.md`): “cabeza pero estoy bien”; amigo/fiebre/medicación = descarte.
- [ ] Urgencia sin categoría (ya en producto) reflejada en QA.
- [ ] Limpiar docs: `contenido-informativo.md`, `asistente-y-chat.md`, README canales.
- [ ] Quitar código muerto de oferta por historial global si quedó tras fase 01/04.
- [ ] Revisar `capability_labels` / booking priority solo en metadata routing.

## Criterio de salida

Plan listo para volcar a `producto/` y borrar `plans/asistente-canales-chat-info/`.
