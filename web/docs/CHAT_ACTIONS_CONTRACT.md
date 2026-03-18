# Contrato de `actions` para Chat (web + apps)

Este documento define el **payload estructurado** que devuelve el endpoint de chat y cómo deben interpretarlo los clientes (web y apps móviles).

## Endpoint

- `POST /api/v1/chat/recibir`

El endpoint **mantiene compatibilidad** retornando siempre `content` como texto plano (para UIs antiguas), y además devuelve un bloque `router` con información estructurada.

## Respuesta (forma general)

```json
{
  "success": true,
  "id": "123",
  "senderId": "BOT",
  "senderName": "Bot",
  "content": "Texto para mostrar al usuario",
  "timestamp": "1710000000",
  "router": {
    "success": true,
    "needs_more_info": false,
    "missing_params": [],
    "response": {
      "text": "Texto para mostrar al usuario",
      "data": {}
    },
    "actions": [],
    "suggestions": [],
    "metadata": {
      "category": "turnos",
      "intent": "crear_turno",
      "confidence": 0.92,
      "detection_method": "rules",
      "parameters_extracted": {}
    },
    "error": null
  }
}
```

## Campo `router.actions`

`router.actions` es un array de **acciones sugeridas** por el orquestador de intents. Las acciones se usan para renderizar **botones** o **deep links** en el cliente.

### Acción mínima (recomendada)

Los clientes deben soportar como mínimo este shape:

```json
{
  "type": "open_route",
  "title": "Abrir formulario",
  "route": "/internacion/create",
  "params": { "id": 123 },
  "prefill": {}
}
```

- `type`: string. Tipos recomendados:
  - `open_route`: navegar a una ruta (web) o deep link (app).
  - `select_patient`: abrir selector/búsqueda de paciente.
  - `open_form`: alias de `open_route` cuando el cliente necesita diferenciar navegación vs modal/form.
- `title`: string, texto del botón.
- `route`: string, ruta canónica (preferentemente igual a `ActionDiscoveryService` / RBAC routes).
- `params`: objeto opcional. Query params para componer la URL final.
- `prefill`: objeto opcional. Datos estructurados para **pre-poblar** el formulario (si el cliente lo soporta).

### Compatibilidad con acciones “legacy”

Algunos handlers pueden devolver acciones descubiertas por `UniversalQueryAgent` con un formato distinto (ej. con `route`, `display_name`, `parameters`, etc.).

Recomendación para clientes:
- Si `action.type` no existe pero `action.route` sí existe, tratarla como `type="open_route"` y usar:
  - `title`: `display_name` o `name` o fallback `"Abrir"`
  - `route`: `route`

## `needs_more_info` y `missing_params`

Si `needs_more_info` es `true`, el cliente debe:
- Mostrar `router.response.text` como pregunta.
- Opcional: mostrar `router.suggestions` como chips/quick replies.
- Enviar el siguiente mensaje del usuario al mismo endpoint (el contexto se persiste en servidor por `senderId` + `botId`).

## Archivos relevantes

- Orquestación: `web/common/components/ConsultaIntentRouter.php`
- Categorías/intents: `web/common/config/chatbot/intent-categories.php`
- Parámetros por intent: `web/common/config/chatbot/intent-parameters.php`
- API chat: `web/frontend/modules/api/v1/controllers/ChatController.php`

