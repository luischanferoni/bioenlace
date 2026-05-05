# Grafo conversacional (Graph)

> **Estado: experimental / laboratorio / prueba.** El grafo y el compilador no sustituyen aún al flujo productivo del asistente (`SubIntentEngine` + intents YAML). La API de chat **no** depende de esto; solo hay un harness de consola y un esquema de ejemplo (`turnos.graph.yaml`). La forma del YAML, los nombres de claves y la integración con `IntentEngine` pueden cambiar sin aviso previo.

Este módulo modela el dominio del asistente como **entidades**, **métodos** (piezas que hablan con `ui_json` / API) y **links** (operaciones de negocio con keywords). A partir de eso, un compilador genera un **`flow_manifest`** compatible con los clientes actuales (pasos, `active_step`, tabs, rutas).

Es una evolución conceptual respecto al YAML “plan fijo” del `SubIntentEngine`: aquí el **orden de los pasos se deduce** de las dependencias `requires` / `provides` sobre el `draft`, en lugar de listar cada paso a mano en un único archivo de intent.

## Objetivos

- **Reutilizar** definiciones: un método (`Entity.metodo`) se declara una vez; varios links o flujos futuros pueden apoyarse en los mismos métodos.
- **Separar** “cómo obtengo un dato” (método + UI) de “qué operación estoy resolviendo” (link + campos requeridos de la entidad).
- **Acotar** qué variantes de UI entran en una operación (p. ej. solo centros filtrados por servicio) mediante **`provider_gates`**, sin duplicar métodos.
- Mantener **salida estable**: `flow_manifest` con `steps`, `next`, `active_step`, `ui.tabs` con `action_id` y `route` hacia `/api/v1/...`.

## Conceptos

### Entidades (`entities`)

Agrupan el conocimiento de dominio:

- **`fields`**: campos del modelo conversacional (p. ej. `Turno` con `id_servicio_asignado`, `slot_id`, …). Los marcados `required: true` definen qué debe estar en el `draft` para completar la operación ligada a esa entidad.
- **`methods`**: piezas atómicas. Cada una expone:
  - `requires`: claves `draft.*` que deben existir antes de poder ejecutar ese paso.
  - `provides`: claves `draft.*` que el usuario completa al usar esa UI.
  - `ui`: al menos `action_id` (p. ej. `servicios.elegir`); opcionalmente `params` con valores `draft.campo`.
  - `step`: metadatos para el manifest (`id`, `label`, y para variantes `tab_id` / `tab_label`).
  - Opcional **`browse: true`** + **`keywords`**: permite intenciones de solo consulta/listado sin un `link` dedicado.

Los métodos se **aplanan** internamente como resolvers `Entity.metodo` (p. ej. `Servicio.elegir_para_turnos`).

### Links (`links`)

Representan una **operación** estable (mismo id que suele usar el producto, p. ej. `turnos.crear-como-paciente`):

- **`keywords`**: detección heurística por substring en el mensaje (orden de evaluación: primero links, luego métodos browse).
- **`entity`** + **`submit_method`**: método final de envío (`Turno.confirmar_creacion_como_paciente` → resolver `Turno.confirmar_creacion_como_paciente`).
- **`action_name`**: título legible del flujo en el manifest.
- **`provider_gates`** (opcional): por cada `draft.*` que hay que satisfacer, se puede exigir que el método candidato tenga ciertos `requires`. Ejemplo: para `draft.id_efector` exigir `draft.id_servicio_asignado` en el método, de modo que un listado “todos los centros” (`requires: []`) **no** entre en el flujo de crear turno.

### Draft

Estado conversacional clave-valor que el cliente envía y el compilador usa para saber qué pasos ya están resueltos y cuál es el **`active_step`**.

## Cómo se compila el `flow_manifest`

1. Se toma el **link** y los **campos requeridos** inferidos de `entities.<entity>.fields`.
2. Se incluye siempre el resolver de **`submit_method`**.
3. Para cada `draft.*` requerido que aún falte, se buscan métodos que **`provide`** esa clave (índice por `provides`).
4. Se aplica **`provider_gates`** al elegir candidatos para cada clave.
5. **Clausura transitiva**: cada método añadido arrastra otros que satisfagan sus `requires` faltantes (siempre respetando gates cuando el contexto es un link).
6. Los resolvers se **agrupan** por `step.id`; varios métodos con el mismo `step.id` generan **tabs** en un único paso lógico.
7. **Orden topológico** entre pasos según intersección `requires` ↔ `provides`.
8. Se asignan punteros **`next`** y se elige **`active_step`** como el primer paso con datos obligatorios aún vacíos en el draft.

Los flujos **browse** arrancan desde un solo resolver (`compileBrowse`) y expanden solo lo necesario; no hay paso de submit del link.

## Archivos

| Ruta | Rol |
|------|-----|
| `schemas/turnos.graph.yaml` | Definición del dominio turnos (MVP). |
| `GraphRegistry.php` | Carga YAML, aplanado de métodos, `detectIntent`, gates, inferencia de requires del link. |
| `GraphFlowManifestCompiler.php` | `compileOperation` / `compileBrowse` → `flow_manifest`. |

## Prueba en consola

Desde `web/`:

```bash
php yii assistant-graph/test --message="quiero sacar turno"
php yii assistant-graph/test --message="qué servicios hay"
php yii assistant-graph/test --operation="turnos.crear-como-paciente" --draft='{"id_servicio_asignado":"6"}'
php yii assistant-graph/test --resolver="Servicio.elegir_para_turnos"
```

## Relación con el resto del Assistant

- Hoy el **chat productivo** sigue usando principalmente **`SubIntentEngine`** + YAML de intents en `SubIntentEngine/schemas/intents/`.
- El **Graph** es la capa donde se prueba la idea de **un solo modelo declarativo** (entidades + métodos + links) y el compilador; integrar el `IntentEngine` / `ChatController` con `GraphRegistry` + `GraphFlowManifestCompiler` es un paso posterior.
- Los **descriptores UI** siguen siendo los JSON en `frontend/modules/api/v1/views/json/...`; el grafo solo referencia `action_id` alineado con esas rutas.

## Extensiones futuras (no obligatorias)

- Varios YAML por dominio y fusión en un registro único.
- Más reglas de detección de intención (sin depender solo de keywords).
- Validación en carga del grafo (ciclos imposibles, `provides` duplicados, etc.).
- Alineación explícita `intent_id` del SubIntent con `link` id del grafo.
