# Manifiesto de flow compilado y despliegue

## Rol de cada artefacto

- **YAML** (`common/components/Assistant/SubIntentEngine/schemas/intents/*.yaml`): fuente conversacional en **runtime**. `SubIntentEngine` la lee en cada interacción de `/asistente/enviar` (y equivalentes). No sustituye el JSON de flow.
- **JSON de flow** (`frontend/modules/api/v1/views/json/<entidad>/<accion>.json` con `ui_type: flow`): manifiesto para **cliente y alineación**: pasos, tabs por paso, rutas `GET` con `ui_definition`, `default_tab`, `requires` / `provides`, mapeo declarativo de `params` y metadatos como `requires_client` (p. ej. geolocalización). Se genera con el comando de consola y debe ir **versionado en el repositorio**.
- **JSON por acción** (mismo directorio, otra `<accion>.json` con `ui_type: ui_json`): descriptor estático para cada `GET` que devuelve `kind: ui_definition`. `AssistantClientOpenEnricher` solo infiere `client_open` tipo `ui_json` si existe plantilla para esa ruta.

## Forma mínima del manifiesto servido al cliente

- `ui_meta.schema_version`: versión del contrato del manifiesto.
- `ui_meta.flow.intent_id`, `entry_subintent_id`, `draft_keys`.
- `ui_meta.flow.steps[]`: cada paso incluye `id`, textos/requisitos del YAML y un bloque `ui` con `tabs[]` y `default_tab` cuando hay variantes de listado (p. ej. por servicio vs cercano).
- Cada tab declara `action_id`, `route` (`/api/v1/...`), `params` (referencias `draft.*` o `client.*`) y opcionalmente `requires_client` (p. ej. `["geolocation"]`).
- `ui_meta.flow.open_ui_hints`: mapa auxiliar de hints (p. ej. `select_efector` / `select_efector_nearby`) alineado con ramas del motor.

En las respuestas conversacionales exitosas, el backend puede adjuntar `flow_manifest`: recorte del manifiesto con `active_subintent_id` y `active_step` para que el cliente renderice tabs sin hardcodear rutas.

## Compilación y comprobación

Desde el directorio `web/` del proyecto:

```bash
php yii flow-manifest/compile
php yii flow-manifest/compile --check
```

- **compile**: lee los YAML de intents y regenera los JSON de flow correspondientes.
- **check**: valida que el archivo en disco coincide con lo generado (útil en CI o antes de commit).

## Despliegue en servidor

Tras `git pull`, los JSON ya deben estar presentes y coherentes con el YAML del commit. El deploy **no** ejecuta el compilador.

## Rutas de dominio vs UI

Los endpoints pensados solo para datos (sin plantilla en `views/json/...`) no deben tener archivo `<accion>.json`, para que no se les infiera `ui_json` por error. Las rutas de listado **por modo** (p. ej. `listar-por-servicio` y `listar-por-servicio-cercano`) son preferibles a una sola ruta con flags opacos.
