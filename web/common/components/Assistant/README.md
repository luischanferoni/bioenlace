# Assistant (UI Intents + Flows)

Este feature agrupa el stack del **asistente**: descubrimiento de UIs, catálogo de intents, resolución de permisos, y ejecución de flujos conversacionales dentro de un intent.

## Componentes

- `IntentEngine/`: entrypoint para clasificar y devolver una acción UI (o arrancar un flow conversacional).
- `Catalog/`: catálogo de UIs sugeribles (hoy basado en YAML).
- `SubIntentEngine/`: motor conversacional *dentro* de un intent (`intent_flow`) basado en YAML.
- `FlowManifest/`: compila YAML de intents a JSON `ui_type=flow` bajo `frontend/modules/api/v1/views/json/...`.
- `UiActions/`: discovery + RBAC + enriquecedores para construir `client_open` y resolver rutas permitidas.

## Fuentes de verdad

- **Conversación por intent**: `SubIntentEngine/schemas/intents/*.yaml`
- **Piezas reutilizables**: `SubIntentEngine/schemas/globals/*.yaml`
- **Artefactos compilados** (`ui_type=flow`): `frontend/modules/api/v1/views/json/<entidad>/<accion>.json`

## Entrypoints importantes

- API chat: `web/frontend/modules/api/v1/controllers/ChatController.php`
- Compilador: `web/console/controllers/FlowManifestController.php`

## Comandos útiles

Desde `web/`:

- Validar que los JSON compilados estén al día:
  - `php yii flow-manifest/compile --check`
- (Alias composer) `composer flow-manifest-check`

