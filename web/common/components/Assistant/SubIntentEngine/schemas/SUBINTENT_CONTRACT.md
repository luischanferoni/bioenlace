# Contrato v1 — manifiestos de flujo (`schemas/intents/*.yaml`)

Fuente de verdad para las claves que **`SubIntentEngine`** lee y combina con el catálogo de acciones UI.  
**No agregar claves nuevas en YAML sin actualizar este documento y el motor** (`SubIntentEngine.php`).

## Raíz del archivo (intent)

| Clave | Uso |
|--------|-----|
| `intent_id` | Identificador estable (alineado con `action_id` del catálogo cuando aplica). |
| `version` | Entero legible para humanos; el motor no lo valida hoy. |
| `action_name`, `description`, `keywords` | Metadatos / descubrimiento. |
| `rbac_route` | Ruta HTTP del **permiso API base** que se asigna al rol (sin `v1`), no una ruta UI/ghost heredada por migración. Ej.: `listar-atenciones-como-paciente`, no `mis-atenciones-como-paciente`. Las rutas hijas se heredan vía `auth_item_child` al migrate; si el rol recibe el padre después, ejecutar la migración de resync correspondiente. |
| `intent_semantics` | Opcional: señal para IA (`goal`, `how`, `preconditions`, etc.). |
| `draft_keys_extra` | Opcional: claves de draft adicionales reconocidas por el producto. |
| `business_rules` | Opcional: reglas `pre_flow` (vía `IntentBusinessRules`). |
| `draft_hydrator` | Opcional: enriquecimiento del `draft` **antes** de `SubIntentEngine::process` (ver abajo). |
| `subintents` | **Obligatorio**: lista ordenada de pasos. |
| `flow_submit` | **Opcional.** Cierre del flujo. El motor detecta automáticamente el **paso terminal** (subintent sin `next` ni `next_routing`) y, cuando ese paso emite `open_ui`, adjunta el descriptor `flow_submit` al envelope (ver más abajo). Si el último paso no tiene `open_ui`, el envelope se emite **solo** con `flow_submit` (texto + botón "Confirmar y enviar"). **No** declarar cierre por subintent: usar solo `flow_submit` en la raíz. |

### `draft_hydrator` (raíz del intent)

Completa el `draft` del request con lógica de dominio **antes** de entrar al motor. El orquestador del chat (`ChatOrchestrator`) **no** lista intents: lee esta clave vía `FlowDraftHydratorService`.

```yaml
draft_hydrator:
  handler: organization.pes_from_servicio   # ID registrado en FlowDraftHydratorRegistry
  require_own_pes: true                     # opciones libres → capa de dominio del handler
```

| Clave | Descripción |
|--------|-------------|
| `handler` | **Obligatorio** si hay bloque. Identificador estable en `FlowDraftHydratorRegistry` (p. ej. `organization.pes_crear_alta`, `organization.pes_from_servicio`). |
| `options` | Opcional: mapa anidado de opciones para el handler. |
| *(otras claves al mismo nivel)* | Se fusionan en `options` (p. ej. `require_own_pes: true`). |

Handlers viven en **servicios de dominio** (`common/components/{Domain}/Service/…`); el registry del asistente solo mapea ID → callable. **No** agregar un handler nuevo sin documentarlo aquí y registrarlo en `FlowDraftHydratorRegistry.php`.

## Nodo `subintents[]` — claves soportadas

Solo deben usarse las siguientes propiedades en cada ítem. Cualquier otra clave es **no portátil** (el motor la ignora hoy).

| Clave | Descripción |
|--------|-------------|
| `id` | **Obligatorio.** Identificador del paso (estable). |
| `assistant_text` | Texto guía para prompt / UI de pasos. |
| `requires` | Lista de campos requeridos en el draft, forma `draft.<clave>` o `<clave>` según el YAML (el motor normaliza internamente). |
| `provides` | Lista de claves que completa la mini-UI de este paso al confirmar selección (o que el POST de una pantalla previa escribe en `draft` vía `data` del cliente). |
| `next` | Id del siguiente subintent, o cadena vacía `""` si no hay siguiente paso lineal. |
| `next_routing` | Alternativa a `next`: lista de ramas `{ when, next }` (ver motor: `draft_equals`, `default`). |
| `open_ui` | Objeto **picker / pantalla embebible** vía catálogo: `action_id`, `params` (valores `draft.*`), `pass_content_as_query` opcional. |
| `chooser` | Objeto con `when_user_says_nearby` / `otherwise`, cada uno con su propio `open_ui` (elección de lista vs cercanía). |
| `hint` | Opcional: `{ entity, match_property }` para resolver menciones del preprocess → `hints[]` en el envelope (`id`, `value`, `draft_field` inferido de `provides`). |

### Forma de `open_ui`

```yaml
open_ui:
  action_id: <action_id del catálogo>
  params:
    <query_param>: "draft.campo_o_literal"
  pass_content_as_query: q   # opcional; ver YAML que lo usan
```

**Query del mini-UI:** valores `draft.<campo>` se resuelven desde el borrador; cualquier otro string no vacío (p. ej. `step: raiz`) se envía **literal** en la URL. Los clientes (`spa-home.js`, `chat_screen.dart` paciente/médico) deben implementar ambas ramas.

### Forma de `flow_submit` (raíz del intent)

```yaml
flow_submit:
  action_id: <action_id del catálogo>      # ej. "turnos.crear-como-paciente"
  params:                                  # mapa apiKey -> "draft.<campo>"
    id_efector: "draft.id_efector"
    id_servicio: "draft.id_servicio"
    id_profesional_efector_servicio: "draft.id_pes"
    id_slot: "draft.id_slot"
```

## Envelope de respuesta del motor

Cada call a `SubIntentEngine::process` devuelve un envelope. Los clientes (web Yii `spa-home.js`, móvil paciente `chat_screen.dart`) deben leerlo así:

| Clave | Cuándo aparece | Uso del cliente |
|--------|---------------|----------------|
| `intent_id`, `subintent_id` | siempre | Mantener estado del flow activo. |
| `text` | siempre | Mensaje del bot. |
| `draft_delta` | siempre (puede ser `{}`) | Merge en el `_draft` local. |
| `open_ui` | cuando hay un paso con UI a mostrar | Abrir mini-UI embebida (`UiJsonScreen` / `renderDynamicUi`). |
| `provides` | con cada `open_ui` | Lista de claves del draft que el paso producirá (sin prefijo `draft.`). El cliente la usa para limpiar el draft al **rebobinar** un paso anterior — ver "Cambio 1" abajo. |
| `flow_submit` | sólo en pasos **terminales** | Descriptor para el botón "Confirmar y enviar" del último paso. Ver tabla siguiente. |
| `flow_manifest` | cuando hay flow activo | Slice del manifiesto para UI (título, tabs). |

### `flow_submit` (descriptor del cierre)

| Clave | Uso |
|--------|-----|
| `action_id` | `<entidad>.<accion>`, para tracing y eventuales fallbacks. |
| `route` | Ruta API canónica `/api/v1/<entidad>/<accion>`. |
| `method` | Hoy siempre `POST`. |
| `body_template` | Mapa `apiKey -> "draft.<campo>"`. **No** está resuelto; el cliente lo expande con su `_draft` local al apretar el botón. |

El cliente, al presionar "Confirmar y enviar", resuelve `body_template` (cualquier valor `draft.x` ausente se reporta como aviso inline) y POSTea a `route`. La respuesta esperada es `kind: ui_submit_result`. No se vuelve a llamar a `/asistente/enviar` para cerrar.

## Semántica de "paso terminal" (Cambio 2)

Un subintent es **terminal** si:

1. No declara `next` ni `next_routing` (el YAML dice "después de este paso no hay otro paso interactivo"), y
2. El intent tiene `flow_submit` con `action_id` válido.

Cuando el motor va a emitir el `open_ui` de un paso terminal, adjunta `flow_submit` al **mismo** envelope. El cliente entonces:

- Muestra el `open_ui` normalmente (p. ej. `kind: list` de slots).
- **El tap en items del último `kind: list` no postea al motor**: sólo mergea local en `_draft`. (Esto cambia el comportamiento "tap = commit" que aplica a los pasos intermedios.)
- Renderiza el botón "Confirmar y enviar" debajo de la UI principal, full-width, **siempre habilitado**. Si el `_draft` no cubre todo `body_template` al apretar, muestra un aviso inline (`Falta elegir: <campo>`) sin enviar.
- Al apretar el botón con todos los campos: POST directo a `flow_submit.route` con el body resuelto.

### Caso "cierre sin UI previa"

Si el último paso no tiene `open_ui` (subintent vacío conectado por `next`), el motor emite un envelope **sólo con `flow_submit`** (sin `open_ui`). El cliente renderiza un mensaje del bot con texto + botón "Confirmar y enviar", sin mini-UI.

### Cuándo NO se marca terminal

- El subintent declara `next_routing`. El motor no puede saber cuál rama tomará sin el valor del draft del paso actual; por seguridad lo deja como **no terminal**. Si una rama de routing es un cierre, modelarla como subintent **sin** `next` (que el motor detecte el cierre cuando salte allí).
- No hay `flow_submit` en la raíz: no hay nada que cerrar.

## Edición hacia atrás (Cambio 1)

Los lists de pasos **previos del mismo flow activo** quedan clickables (no se marcan `flow_superseded`). Cuando el usuario tapea un item de un list previo, el cliente:

1. Elimina del historial todos los mensajes posteriores del mismo flow.
2. Limpia del `_draft` (y del snapshot) las claves listadas en `provides` de cada mensaje eliminado.
3. Aplica el nuevo `draft_delta` del tap y dispara `procesarInteraccion('')` con el draft truncado.
4. El motor recalcula desde el paso editado.

`provides` se emite por eso en **cada** envelope con `open_ui`, terminal o no.

## Cierres POST-only (sin UI en GET)

Algunos `flow_submit.action_id` apuntan a endpoints que **sólo aceptan POST** y devuelven `ui_submit_result`, sin descriptor `ui_definition` (no se puede "abrir" con GET). El `rbac_route` del YAML debe coincidir con el permiso webvimark (sin `v1`), p. ej. `rbac_route: "/api/profesional-agenda/crear-agenda-flow"` → API `POST /api/v1/profesional-agenda/crear-agenda-flow`. Ver `AssistantClientOpenEnricher::isPostOnlyFlowClosureRoute`.

Para esos flujos el motor emite el envelope con `flow_submit` (y eventualmente `open_ui` del último paso si lo hay); el cliente nunca intenta abrir GET sobre la ruta de cierre.

## Alineación entre intents

- Flujos `turnos.*` usan **`flow_submit`** hacia acciones API ya existentes (`crear-como-paciente`, `cancelar-como-paciente`, `reprogramar-como-paciente`).
- `agenda.crear-profesional-flow` usa **`flow_submit`** hacia `profesional-agenda.crear-agenda-flow` tras configurar agenda (con `provides` que marca fin de la pantalla de configuración) o al terminar la rama sin turnos.
- `agenda.editar-agenda-flow` usa **`flow_submit`** hacia `profesional-agenda.editar-agenda-flow` (tras `configurar_agenda` con `provides` `agenda_ui_completed`, o al terminar la selección de servicio si no hay agenda de turnos).
- `agenda.editar-mi-agenda-flow` usa **`flow_submit`** hacia `profesional-agenda.editar-mi-agenda-flow` (misma estructura que editar agenda, sin paso de selección de profesional; servicios propios vía `listar-mis-servicios-en-efector`).

## Referencia de código

- Enriquecimiento de draft: `SubIntentEngine/FlowDraftHydratorService.php`, `FlowDraftHydratorRegistry.php`, manifiestos YAML `draft_hydrator`.
- Implementación motor: `SubIntentEngine/SubIntentEngine.php`
  - `process()`, `buildOpenUiResponse()`, `buildTerminalSubmitOnlyResponse()`.
  - `isTerminalSubintent()`, `buildFlowSubmitTemplate()`, `extractDraftKeys()`.
- Cliente móvil paciente: `mobile/paciente/lib/screens/chat_screen.dart`
  - `_normalizeFlowSubmit()`, `_resolveFlowSubmitBody()`, `_postFlowSubmitFromMessage()`.
  - `_truncateFlowAfter()`, `_lastFlowInteractiveMessageIndex()`, `_messageIsTerminalFlowStep()`.
- Cliente web Yii: `web/frontend/web/js/spa-home.js`
  - `resolveFlowSubmitBody()`, `appendFlowInlineSubmit()`.
