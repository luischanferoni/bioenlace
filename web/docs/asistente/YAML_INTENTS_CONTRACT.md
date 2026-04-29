# Contrato YAML de intents (IntentEngine/SubIntentEngine)

Fuente de verdad: `common/components/Assistant/SubIntentEngine/schemas/` y el código de `IntentEngine`/`SubIntentEngine`.

Este documento describe **qué se configura en YAML** y qué se compila a manifiestos `ui_type: flow`.

## Ubicación

- Intents: `common/components/Assistant/SubIntentEngine/schemas/intents/*.yaml`
- Reutilizables/globales: `common/components/Assistant/SubIntentEngine/schemas/globals/*.yaml` (si aplica)

## Convenciones

- **Draft**: `draft.<campo>` (estado conversacional del usuario)
- **Client**: `client.<campo>` (capacidades/datos del cliente, p. ej. geolocalización)
- **Acciones UI**: `action_id` con forma `entidad.accion` y route `/api/v1/<entidad>/<accion>`

## Intent (top-level)

Campos típicos:

- `action_id` / `intent_id` (según esquema del proyecto)
- `action_name`: nombre humano sugerible
- `description`: descripción para catálogo
- `keywords`: lista de frases para matching
- `subintents`: lista ordenada de pasos conversacionales

## Subintent (paso)

Campos típicos por paso:

- **`id`**: identificador estable del paso (p. ej. `select_efector`)
- **`assistant_text`**: texto que debe mostrar el asistente en ese paso
- **`requires`**: lista de precondiciones (p. ej. `draft.id_efector`)
- **`provides`**: lista de campos que el paso completa (p. ej. `draft.id_rr_hh`)
- **`next`**: id del siguiente paso (o `""` para terminar)
- **`ui`** / `open_ui`: metadatos para abrir mini-UIs (según el engine)
  - Tabs: `ui.tabs[]` con `route` + `action_id` + `params`
  - `requires_client`: capacidades requeridas (p. ej. `["geolocation"]`)

## Parámetros en tabs (`params`)

Mapa declarativo:

```yaml
params:
  id_servicio: draft.id_servicio_asignado
  latitud: client.latitud
  longitud: client.longitud
```

El cliente resuelve:

- `draft.*` desde su snapshot local de draft
- `client.*` desde capacidades (si están disponibles) o debe mostrar error si falta una capability requerida

## Artefacto compilado (manifiesto)

La compilación genera `frontend/modules/api/v1/views/json/<entidad>/<accion>.json` con:

- `ui_type: flow`
- `ui_meta.flow.steps[]` (con `ui.tabs[]`, `default_tab`, `params`, `requires_client`, `requires/provides/next`)

Ver también `FLOW_MANIFEST_Y_DEPLOY.md`.

