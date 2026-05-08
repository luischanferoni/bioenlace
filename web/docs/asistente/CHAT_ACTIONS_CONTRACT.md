# Contrato de `actions` para Chat (web + apps)

Este documento define el **payload estructurado** que devuelve el endpoint de chat y cómo deben interpretarlo los clientes (web y apps móviles).

> Nota: el contrato actual ya no envuelve el resultado en `data` ni usa `message: "Consulta procesada"` como campo útil.
> El backend devuelve `kind` en raíz (p. ej. `kind: intent_flow`) y el texto conversacional va en `text`.

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
        "description": "Vista JSON (screen) + submit unificado para autogestión.",
        "route": "/api/v1/turnos/crear-como-paciente",
        "parameters": { "expected": [], "provided": {} },
        "client_open": {
          "kind": "ui_json",
          "api": { "route": "/api/v1/turnos/crear-como-paciente", "method": "GET|POST" }
        },
        "client_interaction": "ui_asistente_json"
      }
    ]
  }
}
```

## Campo `data.actions`

`data.actions` es un array de **acciones UI** sugeridas por el motor (se renderizan como botones/atajos).

Regla actual: **toda acción debe incluir `client_open.kind`** para que el frontend pueda abrirla **sin heurísticas por URL**.

### Importante: “view” vs “dominio”

- **View (en API)**: significa **descriptor de UI en JSON** (wizard/list/detail) y se obtiene por rutas dedicadas bajo **`/api/v1/<entidad>/<accion>`**.
- **Dominio/datos (en API)**: endpoints como `/api/v1/turnos`, `/api/v1/profesional-agenda/*`, etc. **no son views**; son APIs de negocio consumidas por UIs nativas o por los descriptores.

Regla de arquitectura: el backend no debe invocar controladores web (`frontend/controllers`) para construir UI. Las views JSON se sirven desde plantillas (`views/json/...`) vía los **controladores API por entidad** (ej. `TurnosController`) y `UiDefinitionTemplateManager`/`UiScreenService`.

### Acción mínima (recomendada)

Los clientes deben soportar como mínimo este shape:

```json
{
  "action_id": "turnos.crear-como-paciente",
  "display_name": "Reservar turno",
  "route": "/api/v1/turnos/crear-como-paciente",
  "client_open": {
    "kind": "ui_json",
    "api": { "route": "/api/v1/turnos/crear-como-paciente", "method": "GET|POST" }
  }
}
```

- **`action_id`**: id estable `entidad.accion` (lowercase).
- **`display_name`**: nombre humano para el botón.
- **`route`**: ruta canónica. **Preferencia:** rutas de **views JSON** bajo `/api/v1/...`.
- **`client_open`**: instrucción de apertura de pantalla. El cliente **no debe inferir** cómo abrir basándose en la URL.

#### `client_open.kind` (obligatorio)

- **`ui_json`**: UI dinámica (descriptor JSON) bajo `/api/v1/...`.
  - `client_open.api.route` (string) requerido.
  - `client_open.api.method` (string) sugerido `GET|POST`.

- **`native`**: HTML de una **UI nativa web sin layout Yii** (partial / componente), pensada para fetch + inyección en el shell SPA (no iframe).
  - `client_open.web.path` (string) requerido: URL que devuelve **solo** el markup del componente.
  - `client_open.assets` opcional: `{ css: string[], js: string[] }` para cargar assets una vez.
  - El HTML debe incluir un root `[data-native-component="<name>"]` y el JS `window.BioenlaceNativeComponents[<name>].init(rootEl)`.
  - **Móvil:** solo `client_open.mobile.screen_id` (u otra instrucción explícita de pantalla). La app **no** hace fetch del HTML ni de `assets` web para renderizar ese flujo; implementación **100 % nativa** en Flutter.

- **`intent`**: iniciar un flow conversacional por `intent_id` (YAML).
  - `client_open.intent_id` (string) requerido.
  - El cliente ejecuta el flow vía `POST /api/v1/asistente/enviar` con `intent_id` (y `subintent_id`/`draft` si ya hay estado).

### Móvil: `ui_json` y `custom_widget`

- Con `client_open.kind === "ui_json"`, el cliente llama a `client_open.api.route` (GET/POST) y renderiza el JSON con su motor (p. ej. `UiJsonWizardScreen` en `mobile/packages/shared`).
- Los campos `type: "custom_widget"` se resuelven **solo en el cliente** según `widget_id`. La clave `assets` del descriptor es **irrelevante en móvil** (no se descargan esas URLs para ejecutar JS/CSS).

#### `client_open.presentation` (DEPRECADO)

Ya no se emite. Los motores abren **inline por defecto**. Fullscreen es una acción manual (link) fuera del motor.

## `kind=intent_remediation` (desambiguación)

Cuando el backend detecta ambigüedad antes de ejecutar un flow (por reglas declarativas YAML o por IA),
puede responder:

- `kind: "intent_remediation"`
- `text`: mensaje/pregunta para el usuario
- `rule_id`: id de regla YAML o `"ai_disambiguation"`
- `candidate_intent_id`: intent sugerido originalmente
- `remediation[]`: opciones `{ id, label, intent_id, reset_flow }`
- `match`: info de match `{ action_id, confidence, method, ai? }`
  - `match.ai` (opcional): metadatos explicativos:
    - `system_why`: razón para logs/sistema
    - `user_text`: texto apto para mostrar al usuario
    - `assumptions[]`: supuestos del modelo

Notas:

- **No existe `question`** en el contrato. Para IA, el texto que el cliente debe mostrar vive en `text` (y se recomienda que sea el mismo que `match.ai.user_text`).

El cliente debe renderizar `text` + botones desde `remediation`. Al elegir:

- fijar `intent_id = remediation[*].intent_id`
- si `reset_flow=true`, limpiar `draft` y `subintent_id`
- enviar `POST /api/v1/asistente/enviar` con `content: ""` para iniciar el flow (sin burbuja de usuario adicional).

#### Ejemplo `ui_json` (editar agenda laboral por wizard API)

```json
{
  "action_id": "profesional-agenda.configurar-agenda",
  "display_name": "Configurar agenda laboral",
  "route": "/api/v1/profesional-agenda/configurar-agenda",
  "client_open": {
    "kind": "ui_json",
    "api": { "route": "/api/v1/profesional-agenda/configurar-agenda", "method": "GET|POST" }
  }
}
```

