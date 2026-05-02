# Manifiesto de flow (runtime)

## Fuente de verdad

- **YAML** (`common/components/Assistant/SubIntentEngine/schemas/intents/*.yaml`): única fuente del flujo conversacional. `SubIntentEngine` la lee en cada interacción con `intent_id` en `/api/v1/asistente/enviar`.
- **`FlowManifest`**: arma el mismo contenido que antes vivía en JSON `ui_type: flow` **solo en memoria**, a partir del mismo YAML (pasos, `tabs`, rutas, `open_ui_hints`, `draft_keys`). **No** hay archivo compilado en `views/json` ni comando de compilación.

## Qué recibe el cliente

En respuestas exitosas del motor, el payload puede incluir **`flow_manifest`**: recorte con `active_subintent_id`, `active_step`, lista completa de `steps`, etc., equivalente al modelo anterior pero siempre coherente con el YAML del servidor.

## Despliegue

No hay paso extra: al desplegar solo debe existir el YAML acorde en el repo. No se versionan JSON de flow derivados.

## UI JSON por ruta (no confundir)

Los descriptores **`ui_type: ui_json`** bajo `frontend/modules/api/v1/views/json/<entidad>/<accion>.json` siguen siendo plantillas para endpoints concretos (formularios, listas embebibles). Son independientes del manifiesto de flujo.
