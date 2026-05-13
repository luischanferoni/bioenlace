# Assistant (UI Intents + Flows)

Este feature agrupa el stack del **asistente**: descubrimiento de UIs, catálogo de intents, resolución de permisos, y ejecución de flujos conversacionales dentro de un intent.

## Componentes

- `IntentEngine/`: entrypoint para clasificar y devolver una acción UI (o arrancar un flow conversacional).
- `Catalog/`: catálogo de UIs sugeribles (hoy basado en YAML).
- `SubIntentEngine/`: motor conversacional *dentro* de un intent (`intent_flow`) basado en YAML; incluye evaluación de **`business_rules`** (`pre_flow`) vía `IntentBusinessRules` antes de entrar al flow cuando el entrypoint es `IntentEngine`.
- `FlowManifest/`: construye `flow_manifest` **en runtime** a partir del YAML (sin artefactos `ui_type=flow` en `views/json`).
- `UiActions/`: discovery + RBAC + enriquecedores para construir `client_open` y resolver rutas permitidas.

## Fuentes de verdad

- **Conversación por intent**: `SubIntentEngine/schemas/intents/*.yaml` — contrato de pasos (`open_ui`, `flow_submit`, etc.): `SubIntentEngine/schemas/SUBINTENT_CONTRACT.md`
- **Piezas reutilizables**: `SubIntentEngine/schemas/globals/*.yaml`
- **Mini-UIs** (`ui_json` / wizard): `frontend/modules/api/v1/views/json/<entidad>/<accion>.json`

## Clasificación IA (señal semántica)

Los intents YAML pueden declarar `intent_semantics` (`goal/how/preconditions/constraints/outcome/keyphrases`) para mejorar:

- la clasificación por IA (cuando el texto no matchea keywords literales), y
- la explicación (`match.ai.why`) y desambiguación (`kind=intent_remediation`, `rule_id=ai_disambiguation`).

## Entrypoints importantes

- API chat: `web/frontend/modules/api/v1/controllers/ChatController.php`
 

## Comandos útiles

No hay comando de compilación de `ui_type=flow` hacia `views/json`. El servidor usa YAML en runtime.

