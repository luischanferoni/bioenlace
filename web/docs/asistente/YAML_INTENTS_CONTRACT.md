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
- **`intent_semantics`** (opcional): semántica declarativa del intent para mejorar clasificación IA y explicación (`goal/how/preconditions/constraints/outcome/keyphrases`).
- `subintents`: lista ordenada de pasos conversacionales
- `draft_keys_extra` (opcional): claves de draft usadas sin listarse en `requires`/`provides`
- **`business_rules`** (opcional): reglas evaluadas **antes** de ejecutar el flow cuando el usuario entra al intent vía `IntentEngine` (mensaje raíz o `action_id`). Si una regla aplica, la API puede responder `kind=intent_remediation` en lugar de `intent_flow`.

### `intent_semantics`

Bloque opcional para dar “señal” a la IA más allá de keywords literales.

Campos sugeridos:

- **`goal`**: objetivo del usuario que resuelve este flow.
- **`how`**: cómo se logra el objetivo dentro del flow.
- **`preconditions`**: lista de precondiciones (texto libre).
- **`constraints`**: lista de restricciones de negocio relevantes.
- **`outcome`**: lista de resultados/estado final.
- **`keyphrases`**: lista corta de frases ancla (se agregan automáticamente a `keywords` del catálogo).

### `business_rules`

Lista de reglas. Campos habituales:

- **`id`**: identificador estable (telemetría / logs).
- **`when`**: por ahora solo **`pre_flow`** (evaluación única al arrancar el intent desde el clasificador).
- **`checker`**: nombre registrado en PHP (`IntentBusinessRules` + switch interno). Desconocido ⇒ se ignora con warning en log.
- **`user_message`**: texto corto para el usuario (desambiguación / guía).
- **`remediation`**: lista de opciones con:
  - **`id`**: id de la opción (UI puede marcar selección).
  - **`label`**: texto del botón.
  - **`intent_id`**: intent YAML a iniciar al elegir (siguiente `POST` con `intent_id` + `content: ""` en modo flow).
  - **`reset_flow`**: si es true, el cliente debe limpiar `subintent_id` y `draft` antes de enviar (recomendado).

Checkers actuales (referencia):

- **`content_regex`**: checker genérico declarativo sobre `content` usando `require_any` / `require_all` / `forbid_any`.

### Respuesta `kind=intent_remediation`

Payload típico (raíz de `asistente/enviar` sin `intent_id` en el request):

- `success`, `kind`, `text`, `rule_id`, `candidate_intent_id`, `remediation[]`, `match`.

Notas:

- **`rule_id`** puede ser:
  - el `id` de una `business_rule` YAML (pre-flow), o
  - **`ai_disambiguation`** cuando la desambiguación viene sugerida por IA (sin regla YAML).
- `match.ai` (opcional): explicación de IA para debugging/telemetría:
  - `system_why`: razón para logs/sistema
  - `user_text`: texto apto para el usuario
  - `assumptions[]`: supuestos que hizo el modelo

El cliente muestra `text` y botones desde `remediation`; al pulsar, inicia el flow elegido **sin** simular burbuja de usuario (p. ej. `content: ""` con `intent_id` ya fijado).

## Subintent (paso)

Campos típicos por paso:

- **`id`**: identificador estable del paso (p. ej. `select_efector`)
- **`assistant_text`**: texto que debe mostrar el asistente en ese paso
- **`requires`**: prerequisitos (`draft.*`) que ya deben estar en el draft antes de dar el paso por completo (no repetir claves que ya están en `provides`)
- **`provides`**: campos `draft.*` que completa el paso al elegir/guardar
- **`next`**: id del siguiente paso (cadena vacía para terminar la cadena lineal)
- **`next_routing`** (opcional): lista de reglas `{ when: { draft_equals: { campo: valor } } | { default: true }, next: subintent_id }`; el motor hidrata `draft.servicio_acepta_turnos` desde BD cuando hay `id_servicio`. Si está presente, tiene prioridad sobre `next` para decidir el siguiente paso.
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
