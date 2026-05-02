# Contrato único: UI JSON (`ui_type: ui_json`)

Este documento define el **único contrato** válido para descriptores de UI servidos por endpoints:

- `GET /api/v1/<entidad>/<accion>` → `kind: ui_definition`
- `POST /api/v1/<entidad>/<accion>` → `kind: ui_submit_result` (ok) o `kind: ui_definition` con `success=false` + `errors` (error de validación/negocio)

## Fuente de verdad

- Backend: `common/components/UiDefinitionTemplateManager.php` + `common/components/UiScreenService.php`.
- Templates: `frontend/modules/api/v1/views/json/<entidad>/<accion>.json`.
- Clientes: renderer web (`web/frontend/web/js/spa-home.js`) y Flutter shared (`mobile/packages/shared/lib/ui_json/ui_json_wizard_screen.dart`).

## Shape mínimo (obligatorio)

```json
{
  "ui_type": "ui_json",
  "title": "Opcional",
  "ui_meta": {
    "schema_version": "1",
    "clients": { "*": { "min_app_version": "1.0.0" } }
  },
  "blocks": [
    { "kind": "list", "id": "default", "draft_field": "id_x", "selection": { "mode": "single", "requires_confirmation": true }, "items": [] },
    { "kind": "fields", "id": "default", "fields": [] }
  ]
}
```

## `ui_meta` (metadata global)

- **`ui_meta.schema_version`**: versión del contrato del descriptor.
- **`ui_meta.clients`**: compatibilidad por cliente (obligatorio). El backend evalúa compatibilidad y expone `compatibility` en la respuesta `ui_definition`.

`ui_meta` **no** contiene layout (no `list`, no `steps`, no `wizard_config`).

## `blocks[]` (layout y contenido)

`blocks` define el orden visual. Los clientes renderizan cada bloque en secuencia.

### Block `kind: list`

Propiedades soportadas:

- **`draft_field`** (string, obligatorio): clave de draft que se completa al confirmar.
- **`selection`** (object):
  - `mode`: hoy se soporta `"single"`.
  - `requires_confirmation`: si `true`, muestra botón “Confirmar”; si `false`, confirma al tocar un item.
- **`chips`** (opcional): metadata para filtros/tabs; el flujo conversacional suele aportar los tabs en `flow_manifest`.
- **`item`** (opcional): metadata del tipo de item (p. ej. `"efector"`, `"rrhh"`). Es informativo para el cliente.
- **`items`** (array, obligatorio): items a renderizar (inyectados por controller/servicio).

Shape mínimo de item:

```json
{ "id": "863", "name": "Hospital X" }
```

El cliente puede usar `label` como fallback si no hay `name`.

### Block `kind: fields`

Propiedades soportadas:

- **`fields`** (array, obligatorio): definiciones de campos (mismo formato que venían usando los templates).

Tipos comunes:

- `text`, `number`, `date`, `hidden`, `select`, `radio` (lista de opciones visible; mismo `options` / `option_config` que `select`), `autocomplete`
- `custom_widget` (ver abajo)

#### Layout en grid (Bootstrap-like), opcional por campo

- **`layout`** (objeto, opcional): rejilla de **12 columnas**, mismo contrato en **web** (`spa-home.js`) y **Flutter** (`UiJsonWizardScreen`).
  - `col` (número 1–12): fracción del ancho en una fila.
  - `breakpoint` (opcional): `sm` \| `md` \| `lg` \| `xl` \| `xxl` (por defecto `md`). Por debajo del ancho mínimo de ese breakpoint, el campo ocupa ancho completo (equivalente a apilar).

Si algún campo visible declara `layout.col`, el bloque arma filas; los `hidden` quedan fuera del grid.

#### `custom_widget` dentro de fields

Campo con:

- `type: "custom_widget"`
- `widget_id`: id estable del widget (p. ej. `weekly_scheduler`)
- `value_fields`: lista de claves que el widget escribe en el submit
- `assets` (solo web): `{ css: [], js: [] }` (las apps móviles no descargan assets)
- `initial_values` (opcional): mapa `k -> string` inyectado por backend

## Submit (POST)

- **OK**: `kind: "ui_submit_result"` y `success: true`.
- **Error**: `kind: "ui_definition"` y `success: false` + `errors` + `values` (para re-render con errores).

Los clientes:

- No muestran mensajes de “Guardado correctamente”.
- En flows conversacionales, ante OK deben **avanzar** al siguiente paso (snapshot) sin requerir texto del usuario.

