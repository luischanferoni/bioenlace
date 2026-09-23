# Design — Flows como statechart

## Decisiones

| Tema | Decisión |
|------|----------|
| Modelo | Statechart con contexto (Harel). Serialización con los nombres de XState que un modelo ya sigue: `initial`, `context`, `states`, `description`, `always`, `type: final`, `meta` |
| Salida | La pantalla va en `meta.open_ui` del estado (acción al estar en el estado) |
| Transición | `always`: no hay evento con nombre. El motor avanza cuando el contexto cumple la guarda |
| Guarda | Mapa campo → valor (`guard`). Todas las igualdades del mapa deben cumplirse. El ítem sin `guard` es el comodín y va último |
| Destino lineal | `always` como string: un solo estado siguiente, sin rombo |
| Cierre | `type: final`. No declara `always` |
| Contexto | `context` es la lista de claves (o un mapa cuyas claves son el contexto). Reemplaza `draft_keys_extra` cuando el archivo ya migró. Los valores iniciales los sigue poniendo el hidratador |
| Extensiones | `meta`: `open_ui`, `open_ui_routing`, `chooser`, `provides`, `requires`, `review_prefilled`, `hint`, `flow_submit`, `composer_capture`, `terminal_without_submit` |
| Raíz que no es la máquina | Siguen `intent_id`, `action_name`, `keywords`, `rbac_route`, `flow_submit`, `draft_hydrator`, `intent_semantics.objective`, `capabilities` |
| Guía | En la fase 3 el prompt recibe el statechart (corte de la raíz y sumideros en grafos grandes), no un `outline` redactado |
| Un archivo, un vocabulario | Un intent migrado no conserva `subintents` |

## Compilador (solo durante la migración)

Los lectores actuales recorren pasos con `id`, `next` y `next_routing`. Hasta la fase 3, `StatechartManifest` traduce `states` a ese recorrido **en memoria, al cargar**. El YAML migrado no escribe esas claves. Al cerrar la fase 3 el compilador se borra y los lectores leen `states`.

Eso no es un segundo manifiesto. Es el puente para no reescribir cada lector el mismo día que cambia el piloto.

## Correspondencia

| Statechart | Recorrido actual (solo en memoria) |
|------------|-------------------------------------|
| clave de `states` | `id` |
| `description` | `assistant_text` |
| `initial` | primer paso |
| `always: otro` | `next` |
| `always[].guard` + `target` | `when.draft_equals` + `next` |
| `always` sin `guard` | `when.default` |
| `type: final` | paso sin `next` ni `next_routing` |
| `meta.*` | la misma clave en el paso |
| `context` | `draft_keys_extra` |

## Alternativa descartada

Mantener `subintents` como formato ejecutable y generar una vista XState solo para el prompt. Eso deja la traducción para siempre.

Proyectar el grafo a una máquina de Mealy pura (un estado por cada combinación del draft). El contexto explota el grafo. La pantalla, además, cuelga del estado, no de la arista.

Adoptar SCXML. Es el estándar W3C del mismo modelo, en XML, y los modelos lo siguen peor que el JSON de XState.

## Cierre del plan

Volcar el contrato vigente a `SubIntentEngine/schemas/SUBINTENT_CONTRACT.md` (ya es el contrato vivo) y una nota corta en `producto/asistente-y-chat.md` si hace falta. Borrar `plans/flow-statechart/`.
