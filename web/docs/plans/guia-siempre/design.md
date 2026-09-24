# Design — Guía siempre

## Decisiones

| Tema | Decisión |
|------|----------|
| Destino | `clara` e `incompleta` llaman a la guía. No hay respuesta de flow sin ese paso |
| Apertura | El botón sigue siendo el único que abre el flow. El atajo del inicio también, porque la persona ya eligió |
| Fuera de la guía | Saludo, tema ajeno al HIS, artículo y plantilla |
| Necesidad | Una oración y un estado: activa, satisfecha o descartada. Puede haber varias |
| Memoria | La lista vive en el estado del hilo (`contexto_json`). El turno siguiente la recibe, la actualiza y la vuelve a guardar |
| Guía | Lee solo las expresiones activas. No lee satisfechas, descartadas ni el historial crudo |
| Preprocess | Sigue viendo los mensajes del hilo para poder marcar qué se cerró |
| Tags de estado | Frases en español en el estado, aparte de las extracciones de entidad |
| Cruce | Fold de tildes. Entra un estado si una frase del preprocess está contenida en un tag, o al revés. Se ordena por cantidad de cruces y se corta el tope que ya usa la guía |
| Recorte | La guía recibe el objetivo del intent y las descripciones de los estados que cruzaron |
| Piloto de tags | Solicitar Atención. El resto de los intents puede ir sin tags: si no hay cruce, se adjunta el intent como hoy (objetivo y recorrido chico) |

## Alternativa descartada

Usar las extracciones de entidad (`servicio`, `efector`, `profesional`, `persona`) para elegir el estado. Esa lista nombra menciones que hidratan el borrador. El estado del flow es otro eje.

Hacer que la guía lea la lista completa de estados de la necesidad. Las cerradas sirven para el próximo preprocess, no para la respuesta.

Abrir el flow cuando el cruce es alto. La persona confirma con el botón.

## Cierre del plan

Nota corta en `producto/asistente-y-chat.md`: todo pedido de flow pasa por la guía y el botón. Borrar `plans/guia-siempre/`.
