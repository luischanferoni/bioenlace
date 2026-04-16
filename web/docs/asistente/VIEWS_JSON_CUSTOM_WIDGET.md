# Views JSON: `custom_widget` (contrato mínimo)

Los wizards servidos bajo `/api/v1/<entidad>/<accion>` pueden incluir campos que no son inputs HTML estándar. El **descriptor JSON** es la única pieza común entre clientes; la **implementación visual** es siempre **local** en cada plataforma.

## Estructura del campo

| Clave | Descripción |
|--------|-------------|
| `type` | `"custom_widget"` |
| `name` | Identificador lógico del bloque en el wizard (no tiene que coincidir con las claves POST; los valores van en `value_fields`). |
| `widget_id` | Identificador estable del tipo de widget (ej. `weekly_scheduler`). Cada cliente debe mapear este id a su widget nativo. |
| `value_fields` | Lista de nombres de parámetros que el widget lee y escribe al confirmar el paso (van en el POST del submit, igual que el resto de campos). |
| `assets` | **Solo web (SPA).** `{ "css": ["/css/…"], "js": ["/js/…", "/js/widgets/…"] }` — rutas bajo el webroot del frontend; la SPA los carga con `ensureAssetsLoaded`. **Las apps móviles no descargan ni ejecutan estos assets.** |
| `initial_values` | Opcional. Mapa `nombre_campo → string`; el backend puede rellenarlo vía `UiDefinitionTemplateManager` a partir de query/POST. |
| `props` | Opcional. Metadata para el widget. |
| `label` | Texto visible. |

## Flutter (apps móviles)

- Código compartido: `mobile/packages/shared/lib/ui_json/` (`UiJsonWizardScreen`, `WeeklySchedulerWidget`).
- El motor solo hace **GET/POST** al endpoint de view JSON; resuelve `custom_widget` por `widget_id` **en Dart**.

## Enlaces desde Yii con layout

- Para abrir el wizard en el shell SPA: query `spa_open_ui_json=/api/v1/<entidad>/<accion>` (ver `spa-home.js`).

