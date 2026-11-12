# Fase 02 — Gate de contenido y derivación de escrituras

## Objetivo

Que WhatsApp no muestre datos clínicos ni ejecute altas, bajas o modificaciones. Las lecturas permitidas se responden; las escrituras se completan en la app, que hace el step-up con huella/passkey.

## Estado de la entrega

Implementado:

- Catálogo `Channels/WhatsApp/channel-scope.yaml` + `WhatsAppChannelScopeCatalog`.
- Gate antes del asistente y después del routing raíz.
- Filtro del menú de atajos.
- Copy `whatsapp_channel_redirect` y `whatsapp_write_in_app`.
- Tests del catálogo (clínicos bloqueados, escrituras marcadas).

Pendiente:

- Defensa en profundidad en la API si alguna ruta mutante llegara con `X-App-Client: whatsapp-paciente` (hoy no ocurre).
- Tests de integración con base de datos.
- Si el producto decide permitir escrituras dentro de WhatsApp, volver al diseño de token de step-up + acción pendiente (hoy descartado).

## Tareas

- [x] Declarar los intents permitidos en `channel-scope.yaml` (default bloqueado).
- [x] Gate de contenido antes del asistente.
- [x] Gate después del routing raíz para pedidos en lenguaje natural.
- [x] Filtrar el menú de atajos por el catálogo.
- [x] Marcar `mutating: true` y derivar a la app.
- [x] Copy de canal.
- [x] Tests del catálogo.
- [ ] Test que falle si un intent clínico se agrega al catálogo (revisión de producto).
- [ ] Defensa en profundidad en la API de escritura.
- [ ] Tests de integración con base de datos.

## Criterio de salida

Ningún intent clínico se responde por WhatsApp; ninguna escritura se ejecuta en el canal; el menú no ofrece acciones clínicas; el paciente termina la escritura en la app con huella/passkey.