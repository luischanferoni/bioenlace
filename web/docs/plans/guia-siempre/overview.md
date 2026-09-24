# Overview — Guía siempre

## Objetivo

Todo pedido que hoy es `clara` o `incompleta` pasa por la guía. El preprocess arma las necesidades del hilo y elige qué estados del flow mostrarle. La guía lee solo las necesidades activas. El flow no se abre solo.

## Por qué

Hoy un match claro responde con un botón y no llama a la segunda IA. Un match incompleto sí la llama y le pega un recorrido largo del statechart. La persona, en los dos casos, tiene que tocar un botón para entrar al flow. El atajo de la primera respuesta no aporta el relato de lo que sigue abierto.

## Actores

- Preprocess (1ª IA): necesidades con estado, frases para cruzar con los estados.
- Hilo de chat: persiste la lista en el estado que ya guarda.
- Guía (2ª IA): necesidades activas y el recorte de estados que cruzaron.
- Quien edita el prompt del preprocess y el de la guía.

## Fuera de este plan

- Saludo y tema ajeno al HIS: siguen con texto fijo, sin guía.
- Artículo y plantilla de catálogo: siguen en la misma respuesta, no son un flow.
- Atajo del inicio: la persona ya eligió; el flow abre al tocarlo.
- Extracciones de entidad (oferta del centro, efector, profesional, persona): siguen hidratando el borrador. No eligen el estado.
- El agente no reescribe los prompts. En la fase que los necesita, el plan deja el texto y una persona lo pega.

## Fases

| Fase | Qué |
|------|-----|
| [01](./phases/01-un-solo-destino.md) | `clara` e `incompleta` llaman a la guía. El botón sigue abriendo el flow |
| [02](./phases/02-necesidades-del-hilo.md) | Necesidades con estado, persistidas. La guía recibe solo las activas |
| [03](./phases/03-tags-por-estado.md) | Tags en cada estado. La guía recibe el recorte que cruza con el mensaje |
