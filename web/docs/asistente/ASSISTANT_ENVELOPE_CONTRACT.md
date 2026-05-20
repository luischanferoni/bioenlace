# Contrato de sobre del asistente (chat v3)

Respuesta de `POST /api/v1/asistente/enviar` en **raíz** del JSON (sin `{ success, message, data }` en HTTP 200).

Errores de validación o motor fallido: **HTTP 400** con el formato estándar de la API v1 (`BaseController::error`). No usan este sobre.

## Reglas

- Tres valores de `kind`: `message`, `interactive`, `flow`.
- **Todas las claves de cada variante son obligatorias** en la respuesta; si no aplican, usar sentinels (`""`, `[]`, `{}`, `false`).
- El cliente **no** interpreta `match`, `remediation`, `actions`, ni distingue el motivo de los botones.
- Request sin cambios en Fase 1: `content`, `action_id`, `intent_id`, `subintent_id`, `draft`, `interaction`.

## `message`

```json
{
  "kind": "message",
  "text": "No hay turnos disponibles hoy."
}
```

## `interactive`

Texto + botones que arrancan un intent (`intent_id` en el siguiente POST con `content: ""`).

```json
{
  "kind": "interactive",
  "text": "¿Qué querés hacer?",
  "buttons": [
    { "label": "Reservar turno", "intent_id": "turnos.crear-como-paciente" }
  ]
}
```

- `buttons`: siempre array; vacío si no hay sugerencias.
- Cada botón: `label`, `intent_id` (vacío si el cliente debe enviar `action_id` en el body).

## `flow`

Paso conversacional YAML / SubIntentEngine.

```json
{
  "kind": "flow",
  "text": "Elegí un servicio",
  "session": {
    "intent_id": "turnos.crear-como-paciente",
    "subintent_id": "elegir_servicio",
    "draft_delta": {}
  },
  "manifest": {
    "schema_version": "1",
    "intent_id": "turnos.crear-como-paciente",
    "action_name": "Reservar turno",
    "draft_keys": [],
    "entry_subintent_id": "elegir_servicio",
    "steps": [],
    "active_subintent_id": "elegir_servicio",
    "active_step": {}
  },
  "step": {
    "active": true,
    "action_id": "turnos.listar-servicios-asignados",
    "client_open": {
      "kind": "ui_json",
      "api": {
        "route": "/api/v1/turnos/listar-servicios-asignados",
        "method": "GET",
        "query": {}
      }
    },
    "provides": ["id_servicio_asignado"],
    "pending_fields": []
  },
  "submit": {
    "active": false,
    "route": "",
    "method": "POST",
    "body_template": {}
  }
}
```

### `session`

- `draft_delta`: objeto; `{}` si no hay cambios en este turno.

### `step`

- `active`: `true` si hay mini-UI que montar ahora.
- Si `active` es `false`: `action_id=""`, `client_open.kind=""`, `api.route=""`, `api.method=""`, `api.query={}`.
- `provides`: campos del draft que este paso puede producir (rebobinado de listas).
- `pending_fields`: sustituye `required_draft_fields` (strings `draft.*`).

### `submit`

- `active`: `true` solo en cierre terminal con `flow_submit` en YAML.
- Si `active` es `false`: `route=""`, `body_template={}`.

### `hints`

Siempre array (vacío si no hay). Cada ítem:

```json
{ "entity": "servicio", "id": "12", "value": "Obstetricia", "draft_field": "id_servicio_asignado" }
```

Ver [HINTS_AND_PRESENTATION.md](./HINTS_AND_PRESENTATION.md).

### `manifest`

Slice de `FlowManifest::buildActiveSliceForSubintent`. Si no se puede construir, el servidor emite un **scaffold vacío** con la misma forma (`steps: []`, etc.).

## Implementación servidor

- Builder: `common/components/Assistant/EntryPoints/Chat/Envelope/AssistantEnvelope.php`
- Motores (`IntentEngine`, `SubIntentEngine`) devuelven payload interno con `success: true` y campos de dominio (`text`, `actions`, `remediation`, `intent_id`, `open_ui`, …) **sin** `kind` legacy.
- `ChatOrchestrator` / `OperationalChannel` convierten a sobre v3 con `AssistantEnvelope::fromMotorResponse()`.
- `ChatController` distingue error (`success: false`) de respuesta OK (`isPublicEnvelope()`).

## Clientes

- Web paciente: `frontend/web/js/spa-home.js` → `handleAssistantResponse` (lee `session`, `step`, `manifest`, `submit`, `hints` en raíz)
- Flutter paciente: `assistant_envelope.dart` (`AssistantFlowView`), `chat_screen.dart`, `acciones_service.dart`

## Obsoleto

Ver `CHAT_ACTIONS_CONTRACT.md` (marcado obsoleto). No usar `kind`: `intent_flow`, `ui_intent_match`, `intent_remediation`, `ui_intents_list`, `no_intent_match`.
