# Assistant (UI Intents + Flows)

Este feature agrupa el stack del **asistente**: descubrimiento de UIs, catálogo de intents, resolución de permisos, y ejecución de flujos conversacionales dentro de un intent.

## Componentes

- `IntentEngine/`: entrypoint para clasificar y devolver una acción UI (o arrancar un flow conversacional).
- `Catalog/`: catálogo de UIs sugeribles (hoy basado en YAML).
- `SubIntentEngine/`: motor conversacional *dentro* de un intent (`intent_flow`) basado en YAML.
- `FlowManifest/`: construye `flow_manifest` **en runtime** a partir del YAML (sin artefactos `ui_type=flow` en `views/json`).
- `UiActions/`: discovery + RBAC + enriquecedores para construir `client_open` y resolver rutas permitidas.

## Fuentes de verdad

- **Conversación por intent**: `SubIntentEngine/schemas/intents/*.yaml`
- **Piezas reutilizables**: `SubIntentEngine/schemas/globals/*.yaml`
- **Mini-UIs** (`ui_json` / wizard): `frontend/modules/api/v1/views/json/<entidad>/<accion>.json`

## Entrypoints importantes

- API chat: `web/frontend/modules/api/v1/controllers/ChatController.php`
 

## Comandos útiles

No hay comando de compilación de `ui_type=flow` hacia `views/json`. El servidor usa YAML en runtime.

