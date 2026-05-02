# Contrato YAML de intents (IntentEngine/SubIntentEngine)

Fuente de verdad: `common/components/Assistant/SubIntentEngine/schemas/` y el código de `IntentEngine`/`SubIntentEngine`.

El **`flow_manifest`** que ve el cliente se construye **en runtime** desde el mismo YAML (`FlowManifest`); no hay paso de compilación a JSON.

## Ubicación

- Intents: `common/components/Assistant/SubIntentEngine/schemas/intents/*.yaml`
- Reutilizables/globales: `common/components/Assistant/SubIntentEngine/schemas/globals/*.yaml` (si aplica)

## Convenciones

- **Draft**: `draft.<campo>` (estado conversacional del usuario)
- **Client**: `client.<campo>` (capacidades/datos del cliente, p. ej. geolocalización)
- **Acciones UI**: `action_id` con forma `entidad.accion` y route `/api/v1/<entidad>/<accion>`

## Intent (top-level)

Campos típicos:

- `intent_id`
- `action_name`: nombre humano sugerible
- `description`: descripción para catálogo
- `rbac_route`: ruta de permiso RBAC requerida para listar/ejecutar el flow (ej. `"/api/agenda/editar-agenda-flow"`).
- `keywords`: lista de frases para matching
- `subintents`: lista ordenada de pasos conversacionales
- `draft_keys_extra` (opcional): claves de draft usadas sin listarse en `requires`/`provides`

## Subintent (paso)

Campos típicos por paso:

- **`id`**: identificador estable del paso (p. ej. `select_efector`)
- **`assistant_text`**: texto que debe mostrar el asistente en ese paso
- **`requires`**: prerequisitos (`draft.*`) que ya deben estar en el draft antes de dar el paso por completo (no repetir claves que ya están en `provides`)
- **`provides`**: campos `draft.*` que completa el paso al elegir/guardar
- **`next`**: id del siguiente paso (cadena vacía para terminar la cadena lineal)
- **`open_ui`** / **`chooser`**: metadatos para abrir mini-UIs (`action_id`, `params` → `draft.*`)
- **`submit`**: en el último paso, `action_id` del endpoint de negocio (cuando no hay `open_ui`, el motor puede exponer solo ese submit)

El cliente obtiene tabs/rutas ya **derivadas** del YAML vía `flow_manifest.active_step` cuando aplica.

## Parámetros en tabs (`params`)

Mapa declarativo en YAML, p. ej.:

```yaml
params:
  id_servicio: draft.id_servicio_asignado
```

El cliente resuelve `draft.*` desde su snapshot; `client.*` para capacidades (p. ej. geolocalización) cuando el paso lo requiere.

## Manifiesto servido

El shape de `flow_manifest` (steps, `open_ui_hints`, `draft_keys`, `active_step` con `ui.tabs[]`) es el que genera `FlowManifest` al leer el YAML. Ver `FLOW_MANIFEST_Y_DEPLOY.md`.
