# Contrato de `actions` para Chat (web + apps)

Este documento define el **payload estructurado** que devuelve el endpoint de chat y cómo deben interpretarlo los clientes (web y apps móviles).

## Endpoint

- `POST /api/v1/asistente/enviar`

El endpoint devuelve un payload **estructurado** en `data` con el resultado del motor de intents.

## Respuesta (forma general)

```json
{
  "success": true,
  "message": "Consulta procesada",
  "data": {
    "success": true,
    "kind": "ui_intent_match",
    "explanation": "Abrir: Reservar turno",
    "actions": [
      {
        "action_id": "turnos.crear-como-paciente",
        "display_name": "Reservar turno",
        "description": "UI JSON (screen) + submit unificado para autogestión.",
        "route": "/api/v1/ui/turnos/crear-como-paciente",
        "parameters": { "expected": [], "provided": {} },
        "client_open": { "kind": "ui_json", "api": { "route": "/api/v1/ui/turnos/crear-como-paciente", "method": "GET|POST" } },
        "client_interaction": "ui_asistente_json"
      }
    ]
  }
}
```

## Campo `data.actions`

`data.actions` es un array de **acciones UI** sugeridas por el motor (se renderizan como botones/atajos). Cada acción puede incluir `client_open` para que el cliente sepa cómo abrir la pantalla.

### Importante: “UI” vs “dominio”

- **UI (en API)**: significa **descriptor de UI en JSON** (wizard/list/detail) y se obtiene por rutas dedicadas bajo **`/api/v1/ui/<entidad>/<accion>`**.
- **Dominio/datos (en API)**: endpoints como `/api/v1/turnos`, `/api/v1/agenda/*`, etc. **no son UI**; son APIs de negocio consumidas por UIs nativas o por los descriptores.

Regla de arquitectura: el backend no debe invocar controladores web (`frontend/controllers`) para construir UI. Las UIs JSON se sirven desde plantillas (`views/json/...`) vía los **controladores API por entidad** (ej. `TurnosController`) y `UiDefinitionTemplateManager`/`UiScreenService`.

### Acción mínima (recomendada)

Los clientes deben soportar como mínimo este shape:

```json
{
  "action_id": "turnos.crear-como-paciente",
  "display_name": "Reservar turno",
  "route": "/api/v1/ui/turnos/crear-como-paciente",
  "client_open": {
    "kind": "ui_json",
    "api": { "route": "/api/v1/ui/turnos/crear-como-paciente", "method": "GET|POST" }
  }
}
```

- **`action_id`**: id estable `entidad.accion` (lowercase).
- **`display_name`**: nombre humano para el botón.
- **`route`**: ruta canónica. **Preferencia:** rutas de **UI JSON** bajo `/api/v1/ui/...`.
- **`client_open`**: instrucción de apertura de pantalla. Para UIs JSON: `kind="ui_json"` con `api.route` y `api.method`.
- **`client_interaction`**: string opcional para telemetría/UX (ej. `ui_asistente_json`).

### Fuente de verdad y clases relevantes

- Motor de intents: `web/common/components/IntentEngine/IntentEngine.php`
- Catálogo UI (RBAC + existencia de templates): `web/common/components/IntentCatalog/IntentCatalogService.php`
- Enriquecimiento para apertura en clientes: `web/common/components/Actions/AssistantClientOpenEnricher.php`
- API chat: `web/frontend/modules/api/v1/controllers/ChatController.php`

## `needs_more_info` y `missing_params`

Si `needs_more_info` es `true`, el cliente debe:
- Mostrar `router.response.text` como pregunta.
- Opcional: mostrar `router.suggestions` como chips/quick replies.
- Enviar el siguiente mensaje del usuario al mismo endpoint (el contexto se persiste en servidor por `senderId` + `botId`).

## Archivos relevantes

- API chat: `web/frontend/modules/api/v1/controllers/ChatController.php`
- Motor de intents: `web/common/components/IntentEngine/IntentEngine.php`
- Catálogo UI: `web/common/components/IntentCatalog/IntentCatalogService.php`

