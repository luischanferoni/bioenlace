# Fase 02 — Step-up y gate de escrituras

## Objetivo

Impedir que cualquier alta, baja o modificación se ejecute sin una verificación vigente que coincida con la Persona vinculada.

## Tareas

- [ ] Declarar `whatsapp: allowed` en los intents que pueden responderse por WhatsApp (turnos, información, trámites). Default: bloqueado.
- [ ] Gate de contenido en el canal: si el intent no declara `whatsapp: allowed`, responder con enlace a la app; no ejecutar ni mostrar datos.
- [ ] Test que falle si un intent clínico (laboratorio, receta, atención, internación) declara `whatsapp: allowed` sin decisión de producto.
- [ ] Declarar `assurance: step_up` en los manifiestos de intents de `create`, `update` y `delete` que actúan como paciente.
- [ ] Test de arquitectura que recorra los intents y falle si uno de escritura no declara assurance.
- [ ] Crear `StepUpAssuranceService`: emite token, lo consume, valida TTL y compara identidad.
- [ ] Persistir la acción pendiente (`asistente_whatsapp_accion_pendiente`) con ruta, cuerpo, hash del draft, token y expiración.
- [ ] En `WhatsAppInboundService`, detectar intent/callback mutante y exigir step-up antes de continuar.
- [ ] Responder con enlace opaco de step-up cuando falta verificación; no ejecutar nada todavía.
- [ ] Consumir el token y ejecutar el `flow_submit` una sola vez, en transacción.
- [ ] Defensa en profundidad: la API de escritura valida assurance cuando el cliente es `whatsapp-paciente`.
- [ ] Reutilizar el mismo caso de uso/API que la app; no crear un escritor paralelo.
- [ ] Si el paso requiere `client_open`, responder con deep link a la app y no intentar ejecutarlo por WhatsApp.
- [ ] Tests: escritura sin step-up, step-up vencido, token reutilizado, identidad que no coincide, doble callback, reintento de Meta.

## Criterio de salida

Ninguna operación mutante se ejecuta sin token vigente y coincidencia de identidad; el token es de un solo uso; la operación es idempotente; el canal no duplica la lógica de escritura.
