## Views embebibles (chat) — contrato v1

Este documento define el contrato para **views JSON embebibles** que se renderizan dentro del chat (web y Flutter).

### 1. Objetivo

Una view embebible es un descriptor JSON servido bajo `/api/v1/<entidad>/<accion>` que:

- puede mostrar **chips/filtros** opcionales
- puede mostrar un **listado** de ítems seleccionables (patrón “picker”)
- exige **confirmación obligatoria** antes de aplicar cambios al `draft`
- al confirmar, produce un `draft_delta` (parcial) que el cliente aplica localmente y reenvía en el snapshot siguiente

> Nota: “picker” deja de ser una categoría del sistema. Es un **patrón de UI** cuando el descriptor incluye `ui_meta.picker`.

### 2. Principios

- El servidor (SubIntentEngine) es stateless: no guarda selección “pendiente”.
- El cliente conserva:
  - `draft`
  - `pending_selection` (si aplica)
  - `current_intent_id` y `current_subintent_id`
- La confirmación es una interacción tipada (`confirm_selection`) que el cliente envía al backend junto al `selection_payload`.

### 3. Shapes (request/response) — nivel asistente

#### Request (wizard snapshot)

```json
{
  "intent_id": "turnos.crear-como-paciente",
  "subintent_id": "select_efector",
  "draft": { "id_servicio": "12" },
  "content": "cerca de casa",
  "interaction": null
}
```

#### Response (SubIntentEngine)

```json
{
  "success": true,
  "text": "Seleccioná un efector.",
  "open_ui": {
    "action_id": "efectores.elegir",
    "client_open": {
      "kind": "ui_json",
      "presentation": "inline",
      "api": { "route": "/api/v1/efectores/elegir", "method": "GET|POST" }
    }
  },
  "draft_delta": {}
}
```

### 4. Contrato de confirmación

#### Interacción de confirmación (request)

```json
{
  "interaction": {
    "kind": "confirm_selection",
    "decision": "confirm",
    "selection": {
      "id": "123",
      "label": "Hospital Central"
    }
  }
}
```

### 5. Patrón “picker” (nivel descriptor UI JSON)

Cuando una view embebible es un picker, debe exponer en su descriptor `ui_meta.picker`:

```json
{
  "ui_meta": {
    "picker": {
      "selection": { "mode": "single", "requires_confirmation": true },
      "chips": [{ "id": "nearby", "label": "Cerca" }],
      "list": { "item_kind": "efector", "id_field": "id", "label_field": "nombre" }
    }
  }
}
```

