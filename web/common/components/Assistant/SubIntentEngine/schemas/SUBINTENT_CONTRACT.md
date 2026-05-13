# Contrato v1 — manifiestos de flujo (`schemas/intents/*.yaml`)

Fuente de verdad para las claves que **`SubIntentEngine`** lee y combina con el catálogo de acciones UI.  
**No agregar claves nuevas en YAML sin actualizar este documento y el motor** (`SubIntentEngine.php`).

## Raíz del archivo (intent)

| Clave | Uso |
|--------|-----|
| `intent_id` | Identificador estable (alineado con `action_id` del catálogo cuando aplica). |
| `version` | Entero legible para humanos; el motor no lo valida hoy. |
| `action_name`, `description`, `keywords` | Metadatos / descubrimiento. |
| `rbac_route` | Ruta HTTP de permiso (sin `v1`), alineada a la acción API real que webvimark puede descubrir (p. ej. cierre POST del flujo o pantalla principal del intent). |
| `intent_semantics` | Opcional: señal para IA (`goal`, `how`, `preconditions`, etc.). |
| `draft_keys_extra` | Opcional: claves de draft adicionales reconocidas por el producto. |
| `business_rules` | Opcional: reglas `pre_flow` (vía `IntentBusinessRules`). |
| `subintents` | **Obligatorio**: lista ordenada de pasos. |
| `flow_submit` | **Opcional.** Cierre del flujo: cuando el motor determina paso terminal (sin siguiente `open_ui` pendiente, `next` vacío o rama vacía) y el draft cumple `requires`/`provides`, emite `open_ui` con el `client_open` de esta acción (GET descriptor + POST mutación), igual que antes el `submit` por subintent. **No** declarar cierre por subintent: usar solo `flow_submit` en la raíz. Además, si el mapeo `params` produce al menos un valor, el motor incluye **`flow_submit_request`** (`method`, `route` `/api/v1/...`, `body` clave→valor desde el draft) para que los clientes muestren un **botón de confirmación** en el último paso sin depender de abrir otra pantalla cuando `client_open` sea `null`. |

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

### Forma de `open_ui`

```yaml
open_ui:
  action_id: <action_id del catálogo>
  params:
    <query_param>: "draft.campo_o_literal"
  pass_content_as_query: q   # opcional; ver YAML que lo usan
```

### Forma de `flow_submit` (raíz del intent)

```yaml
flow_submit:
  action_id: <action_id del catálogo>
  params:   # opcional; mismas reglas que mapean draft → query del client_open
    id: "draft.id"
```

### Payload JSON: `flow_submit_request` (respuesta del motor)

Cuando el cierre del flujo emite `flow_submit`, el motor puede adjuntar (además de `open_ui`):

| Clave | Uso |
|--------|-----|
| `method` | Hoy siempre `POST`. |
| `route` | Ruta API canónica `/api/v1/<entidad>/<accion>` derivada de `action_id`. |
| `body` | Objeto string→string: cada clave del `params` del YAML cuyo valor es `draft.<campo>` y el draft tiene valor. Es el cuerpo del POST de cierre (`UiScreenService` / `kind: ui_submit_result`). |

**Cuándo usar `flow_submit`:**

1. **Paso dedicado** sin listado previo: el último dato ya está en `draft` y solo falta abrir la pantalla de cierre (ej. cancelar turno con `id`).
2. **Tras un picker + `next: ""`**: mientras falten `requires` / `provides`, el motor abre `open_ui` del paso; cuando el draft está completo y no hay siguiente paso, emite el `client_open` del `flow_submit` (ej. slot elegido → `turnos.crear-como-paciente`). Si no hay descriptor en catálogo/RBAC, **`flow_submit_request`** permite al cliente POSTear el cierre **en línea** (p. ej. botón bajo la misma mini-UI de slots) sin forzar un segundo `open_ui` fallido.
3. **Rama sin `open_ui` siguiente**: si `next` apunta a un subintent “vacío” (sin UI, `next` vacío) y el draft ya cumple, el motor puede emitir `flow_submit` desde ese id de paso.

**Cuándo no hace falta `flow_submit`:**

- Toda la interacción, incluido el cierre satisfactorio para el usuario, ocurre dentro de una `open_ui` que ya persiste en su POST (p. ej. condición laboral). Aun así puede existir `rbac_route` apuntando a una ruta de permiso “stub” si el producto la usa solo para RBAC.

**Importante:** si un paso con `open_ui` y `next: ""` no declara `provides` para “haber completado” esa pantalla, el motor puede considerar el draft ya completo y disparar `flow_submit` demasiado pronto. Añadir `provides` (p. ej. un flag que el POST de esa UI devuelve en `data`) o encadenar un paso explícito.

## Alineación entre intents

- Flujos `turnos.*` usan **`flow_submit`** hacia acciones API ya existentes (`crear-como-paciente`, `cancelar-como-paciente`, `reprogramar-como-paciente`).
- `agenda.crear-profesional-flow` usa **`flow_submit`** hacia `profesional-agenda.crear-agenda-flow` tras configurar agenda (con `provides` que marca fin de la pantalla de configuración) o al terminar la rama sin turnos.
- `agenda.editar-agenda-flow` usa **`flow_submit`** hacia `profesional-agenda.editar-agenda-flow` (tras `configurar_agenda` con `provides` `agenda_ui_completed`, o al terminar la selección de servicio si no hay agenda de turnos).

## Referencia de código

- Implementación: `SubIntentEngine/SubIntentEngine.php` (`resolveOpenUiForSubintent`, `emitFlowSubmitPayload`, `applyDraftParamsMapToOpenUi`).
