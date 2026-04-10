# UI JSON: `custom_widget` (contrato mínimo)

Los wizards servidos bajo `/api/v1/ui/<entidad>/<accion>` pueden incluir campos que no son inputs HTML estándar. El **descriptor JSON** es la única pieza común entre clientes; la **implementación visual** es siempre **local** en cada plataforma.

## Estructura del campo

| Clave | Descripción |
|--------|-------------|
| `type` | `"custom_widget"` |
| `name` | Identificador lógico del bloque en el wizard (no tiene que coincidir con las claves POST; los valores van en `value_fields`). |
| `widget_id` | Identificador estable del tipo de widget (ej. `weekly_scheduler`). Cada cliente debe mapear este id a su widget nativo. |
| `value_fields` | Lista de nombres de parámetros que el widget lee y escribe al confirmar el paso (van en el POST del submit, igual que el resto de campos). |
| `assets` | **Solo web (SPA).** `{ "css": ["/css/…"], "js": ["/js/…", "/js/widgets/…"] }` — rutas bajo el webroot del frontend; la SPA los carga con `ensureAssetsLoaded`. **Las apps móviles no descargan ni ejecutan estos assets.** |
| `initial_values` | Opcional. Mapa `nombre_campo → string`; el backend puede rellenarlo vía `UiDefinitionTemplateManager` a partir de query/POST. |
| `props` | Opcional. Metadata para el widget (ej. precisión de la grilla en web). |
| `label` | Texto visible. |

### Qué significa el CSV en `lunes_2`, `martes_2`, etc.

- **`lunes_2`, `martes_2`, …** son **nombres de parámetros** acordados en el descriptor (`value_fields`). El sufijo `_2` u otros es convención del dominio (p. ej. plantilla o servicio); no implica un formato especial en el nombre.
- El **valor** de cada parámetro es un **string**: lista de **enteros separados por comas**, sin espacios obligatorios (ej. `"8,9,14"`). En el widget `weekly_scheduler` alineado a la grilla web, cada entero es un **índice de franja horaria 0–23** (hora del día), igual que en `scheduler.js` / SPA.

## Web (SPA)

- `spa-home.js` renderiza un contenedor `.bio-ui-custom-widget` con `data-bio-ui-widget="<widget_id>"`, inputs `hidden` por cada entrada de `value_fields`, y monta la tabla cuando corresponde.
- Tras cargar `assets`, se llama a `window.BioenlaceUiWidgets[<widget_id>].init(rootElement, fieldDefinition)`.
- Widget semanal: `web/frontend/web/js/widgets/ui-widget-weekly-scheduler.js` (junto con `/js/scheduler.js`, `/css/scheduler.css` declarados en el JSON).

## Flutter (apps móviles)

- Código compartido: `mobile/packages/shared/lib/ui_json/` (`UiJsonWizardScreen`, `WeeklySchedulerWidget`). **No** duplicar en `medico/lib` ni `paciente/lib` salvo puntos de entrada propios de cada app.
- El motor solo hace **GET/POST** al endpoint UI JSON; resuelve `custom_widget` por `widget_id` **en Dart**. No se obtiene HTML ni JS del descriptor para ejecutarlo en WebView como sustituto del widget.

## Pantalla de ejemplo (agenda laboral RRHH)

- Descriptor: `web/frontend/modules/api/v1/views/json/rrhh/editar-agenda.json`
- API: `GET|POST /api/v1/ui/rrhh/editar-agenda` → `RrhhController::actionEditarAgenda` + `RrhhAgendaUiService`.
- Permisos (webvimark): registrar manualmente la ruta de API sin segmento `v1` si así está el proyecto (p. ej. `/api/rrhh/editar-agenda`), según convención existente. **No** hay migración automática dedicada a estas rutas en el repositorio.

## Enlaces desde Yii con layout

- Para abrir el wizard en el shell SPA: query `spa_open_ui_json=/api/v1/ui/rrhh/editar-agenda` (u otra ruta UI JSON), tal como documenta `spa-home.js`.
