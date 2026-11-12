# Fase 03 — Costos de IA y WhatsApp

## Objetivo

Cerrar las brechas de atribución para que los costos del panel salgan de datos persistidos y no de logs.

## IA

- Agregar una tabla de uso de IA con: fecha, contexto, modelo, tokens de entrada, cacheados, salida, `id_efector`, `id_persona` cuando aplique y referencia a la atención o conversación.
- Escribirla desde el punto donde hoy se registra el uso (`AICostTracker` o el cliente de IA), sin cambiar la lógica de negocio.
- Migración con `common/migrations/`; sin `ALTER` manual.
- El panel calcula el costo con el precio de referencia del catálogo de IA, no con un valor hardcodeado.

## WhatsApp

- Agregar `id_efector` a `AsistenteWhatsappMensajeEstado`, resuelto al enviar el mensaje.
- Si el mensaje no se puede atribuir, guardar `NULL` y mostrarlo como «sin asignar».
- El panel cuenta mensajes por estado, categoría y `billable`.
- El costo de Meta se calcula con la tarifa vigente declarada en el catálogo o en la metadata de costos; no se hardcodea en la vista.

## Didit

- Contar verificaciones por método y resultado desde `AsistenteWhatsappVerificacion`.
- Atribuir al efector por `id_persona` y los turnos del paciente; si hay varios efectores, informar «sin asignar» o prorratear solo si producto lo define.
- El costo por sesión sale de la tabla de precios de Didit vigente.

## Conciliación

- Comparar el total estimado del panel con la factura de Meta y el consumo de Didit del período.
- Si la diferencia supera un umbral acordado, el panel lo muestra como alerta de datos, no como error de negocio.

## Tareas

- [ ] Migración de uso de IA.
- [ ] Hook de escritura de uso de IA.
- [ ] Migración de `id_efector` en el estado de mensajes de WhatsApp.
- [ ] Atribución al enviar y fallback «sin asignar».
- [ ] Cálculo de costos con tarifas vigentes.
- [ ] Conciliación con facturas del período.
- [ ] Tests de agregación y de atribución.

## Criterio de salida

El panel muestra costo de IA, WhatsApp y Didit por efector y período con datos persistidos; los montos no atribuibles se informan como tales y la conciliación cierra dentro del umbral acordado.
