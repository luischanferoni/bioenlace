# Fase 2 — Necesidades del hilo

## Objetivo

El preprocess devuelve una o varias necesidades, cada una con estado. Se guardan en el hilo. La guía recibe solo las activas.

## Contrato que tiene que devolver la 1ª IA

Cada ítem tiene una oración (hechos: dónde, cómo, desde cuándo, para quién) y un estado:

- activa: lo que quiere ahora y todavía no se resolvió
- satisfecha: ya se cumplió en este hilo
- descartada: la dejó de lado o dijo que no

Si no hay ninguna activa, igual se devuelven las cerradas que explican el hilo. No se inventan necesidades ni se copia el historial.

El texto del prompt lo pega una persona. El agente no edita ese archivo.

## Checklist

- [ ] Normalizar la lista (oración + estado). Descartar estados desconocidos
- [ ] Guardarla en el estado del hilo y volver a leerla en el turno siguiente
- [ ] Pasar al preprocess la lista guardada junto con los mensajes, para que pueda actualizarla
- [ ] La guía arma `{necesidad_usuario}` solo con las oraciones activas
- [ ] Test: una satisfecha y una activa; la guía ve únicamente la activa

## Qué no entra

Tags por estado ni el recorte del statechart.
