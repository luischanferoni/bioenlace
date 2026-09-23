# Overview — Flows como statechart

## Objetivo

Que cada intent YAML sea un statechart con contexto: estados de control, transiciones sin evento (`always`), guardas de igualdad sobre el contexto y extensiones de producto en `meta`. Un modelo de lenguaje ya conoce esa forma. El mismo archivo lo ejecuta `SubIntentEngine`.

## Por qué

El grafo de Solicitar Atención ya es esa máquina (bifurcación en el motivo, ramas que vuelven a la reserva, draft como memoria). Hoy está escrito con claves propias (`subintents`, `next_routing`, `draft_equals`) y la guía recibe un párrafo `outline` escrito a mano. Dos vocabularios obligan a traducir.

## Actores

- Motor de flujos (`SubIntentEngine` y quien carga el YAML).
- Guía (2ª IA), que debe leer el statechart y no un resumen paralelo.
- Quien edita intents.

## Fuera de este plan

- Reescribir prompts de canal (los edita una persona).
- Cambiar keywords, catálogo inteligente o routing clara / incompletas.
- Expresiones de guarda arbitrarias (JavaScript, CEL). La guarda de esta etapa es igualdad de campos del contexto, el mismo poder que `draft_equals`.

## Fases

| Fase | Qué |
|------|-----|
| [01](./phases/01-vocabulario-y-carga.md) | Vocabulario, compilador en la carga, piloto de un intent lineal |
| [02](./phases/02-migrar-intents.md) | Pasar el resto de intents, empezando por Solicitar Atención |
| [03](./phases/03-lectores-y-guia.md) | Los lectores hablan `states` directo, se borra el compilador y el `outline` de ramas |
