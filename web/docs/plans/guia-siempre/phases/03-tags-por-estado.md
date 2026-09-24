# Fase 3 — Tags por estado

## Objetivo

Cada estado puede declarar frases. El preprocess cruza lo que dijo la persona con esas frases. La guía recibe el objetivo del intent y solo los estados que cruzaron.

## Cruce

Fold de tildes. Hay cruce si una frase del mensaje está contenida en un tag del estado, o al revés. Se ordena por cantidad de cruces. Se respeta el tope de intents que la guía ya usa. Las extracciones de entidad no entran en este cruce.

Si un intent no tiene tags, se adjunta como hoy: objetivo y, si el grafo es chico, la lista de descripciones.

## Piloto

Solicitar Atención. Los estados del motivo, del estudio y de la renovación llevan frases propias. Un mensaje de renovar medicación no arrastra el recorrido de urgencia ni el de ecografía.

## Checklist

- [ ] El contrato del estado documenta las frases en `meta`
- [ ] Piloto en Solicitar Atención
- [ ] El formateador de la guía lista solo los estados que cruzaron
- [ ] Test: «renovar medicación» no incluye la descripción de urgencia; «ecografía» no incluye la de renovación

## Qué no entra

Reescribir el resto de los intents. Pueden sumar frases después, sin cambiar el motor.
