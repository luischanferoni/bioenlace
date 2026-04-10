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
        "client_open": { "kind": "ui_json", "presentation": "fullscreen", "api": { "route": "/api/v1/ui/turnos/crear-como-paciente", "method": "GET|POST" } },
        "client_interaction": "ui_asistente_json"
      }
    ]
  }
}
```

## Campo `data.actions`

`data.actions` es un array de **acciones UI** sugeridas por el motor (se renderizan como botones/atajos).

Regla actual: **toda acción debe incluir `client_open.kind`** para que el frontend pueda abrirla **sin heurísticas por URL**.

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
    "presentation": "fullscreen",
    "api": { "route": "/api/v1/ui/turnos/crear-como-paciente", "method": "GET|POST" }
  }
}
```

- **`action_id`**: id estable `entidad.accion` (lowercase).
- **`display_name`**: nombre humano para el botón.
- **`route`**: ruta canónica. **Preferencia:** rutas de **UI JSON** bajo `/api/v1/ui/...`.
- **`client_open`**: instrucción de apertura de pantalla. El cliente **no debe inferir** cómo abrir basándose en la URL.

#### `client_open.kind` (obligatorio)

- **`ui_json`**: UI dinámica (descriptor JSON) bajo `/api/v1/ui/...`.
  - `client_open.api.route` (string) requerido.
  - `client_open.api.method` (string) sugerido `GET|POST`.

- **`native`**: HTML de una **UI nativa web sin layout Yii** (partial / componente), pensada para fetch + inyección en el shell SPA (no iframe).
  - `client_open.web.path` (string) requerido: URL que devuelve **solo** el markup del componente.
  - `client_open.assets` opcional: `{ css: string[], js: string[] }` para cargar assets una vez.
  - El HTML debe incluir un root `[data-native-component="<name>"]` y el JS `window.BioenlaceNativeComponents[<name>].init(rootEl)`.
  - **Móvil:** solo `client_open.mobile.screen_id` (u otra instrucción explícita de pantalla). La app **no** hace fetch del HTML ni de `assets` web para renderizar ese flujo; implementación **100 % nativa** en Flutter.

### Móvil: `ui_json` y `custom_widget`

- Con `client_open.kind === "ui_json"`, el cliente llama a `client_open.api.route` (GET/POST) y renderiza el JSON con su motor (p. ej. `UiJsonWizardScreen` en `mobile/packages/shared`).
- Los campos `type: "custom_widget"` se resuelven **solo en el cliente** según `widget_id`. La clave `assets` del descriptor es **irrelevante en móvil** (no se descargan esas URLs para ejecutar JS/CSS).

#### `client_open.presentation` (obligatorio para `ui_json` y `native` vía asistente)

Indica **cómo** abre el shell SPA el mismo contenido (el payload sigue siendo sin layout):

- **`inline`**: panel expandido dentro de la card / área actual.
- **`fullscreen`**: capa/stack que ocupa toda la ventana del shell (sigue siendo fetch del mismo partial o JSON; no es “página Yii con `<html>` completo”).

Valores por defecto si falta el campo: `ui_json` → `fullscreen`; `native` → `inline`.

**Navegación “con layout” de sitio (browser completo)** no forma parte de este contrato: enlaces normales o `@no_intent_catalog` según producto.

#### Ejemplo `ui_json` (editar agenda laboral por wizard API)

Flujo equivalente sustituye antiguas pantallas Yii-only; la ruta canónica es la **UI JSON**:

```json
{
  "action_id": "rrhh.editar-agenda",
  "display_name": "Editar agenda laboral",
  "route": "/api/v1/ui/rrhh/editar-agenda",
  "client_open": {
    "kind": "ui_json",
    "presentation": "fullscreen",
    "api": {
      "route": "/api/v1/ui/rrhh/editar-agenda",
      "method": "GET|POST"
    }
  }
}
```

En **web**, el mismo endpoint alimenta la SPA (wizard dinámico). En **móvil**, solo se consume el JSON; el `weekly_scheduler` del descriptor se implementa en Dart compartido, no vía assets del servidor.

#### Ejemplo `native` + `inline` (solo web SPA)

```json
{
  "action_id": "native.ejemplo.inline",
  "display_name": "Componente nativo web",
  "route": "/algun-controlador/alguna-accion",
  "client_open": {
    "kind": "native",
    "presentation": "inline",
    "web": { "path": "/algun-controlador/alguna-accion" },
    "mobile": { "screen_id": "algun.screen.id" },
    "assets": {
      "css": ["/css/ejemplo.css"],
      "js": ["/js/ejemplo.js"]
    }
  }
}
```

`client_open.web.path` se resuelve con **`Url::to()`** sobre la ruta canónica `/<controller>/<action>` cuando el catálogo descubre esa acción. En móvil aplica solo `screen_id` (sin fetch del partial ni de `assets`).

### Cómo declarar `native` en el catálogo (metadata en docblock)

El catálogo se descubre desde `frontend/controllers/*Controller.php`. El motor asume que estas acciones devuelven **HTML sin layout** (partial). Tags:

- `@native_ui_path /<ruta>` — **opcional**; sobrescribe el path por defecto si el fetch debe ser distinta a la ruta canónica.
- `@spa_presentation inline` o `@spa_presentation fullscreen`
- `@native_assets_css /css/a.css,/css/b.css`
- `@native_assets_js /js/a.js,/js/b.js`
- `@mobile_screen_id agenda.crear` (opcional; default `<controller>.<action>`)

- **`client_interaction`**: string opcional para telemetría/UX (ej. `ui_asistente_json`).

### Fuente de verdad y clases relevantes

- Motor de intents: `web/common/components/IntentEngine/IntentEngine.php`
- Catálogo UI (RBAC + existencia de templates): `web/common/components/IntentCatalog/IntentCatalogService.php`
- Path fetch HTML nativo (canónico + `@native_ui_path`): `web/common/components/Actions/ActionDiscoveryService::resolveNativeWebFetchPath()`
- Catálogo nativo + `client_open`: `web/common/components/IntentEngine/UiActionCatalog.php`
- Atajos inicio (acciones comunes): `web/common/components/Services/Actions/CommonActionsService.php`
- Enriquecimiento para apertura en clientes: `web/common/components/Actions/AssistantClientOpenEnricher.php`
- API chat: `web/frontend/modules/api/v1/controllers/ChatController.php`
- Campos `custom_widget` en wizards UI JSON: `web/docs/UI_JSON_CUSTOM_WIDGET.md`

## `needs_more_info` y `missing_params`

Si `needs_more_info` es `true`, el cliente debe:
- Mostrar `router.response.text` como pregunta.
- Opcional: mostrar `router.suggestions` como chips/quick replies.
- Enviar el siguiente mensaje del usuario al mismo endpoint (el contexto se persiste en servidor por `senderId` + `botId`).

## Archivos relevantes

- API chat: `web/frontend/modules/api/v1/controllers/ChatController.php`
- Motor de intents: `web/common/components/IntentEngine/IntentEngine.php`
- Catálogo UI: `web/common/components/IntentCatalog/IntentCatalogService.php`

