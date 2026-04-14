# StepIntentEngine + Wizard conversacional (turnos paciente)

## Objetivo

Implementar un wizard conversacional para **reservar turno como paciente** donde:

- El **estado del wizard es 100% del cliente** (Flutter / web). El servidor opera **stateless** respecto del wizard: cada request incluye un **snapshot** (paso actual + draft).
- El backend sigue siendo la **única fuente de negocio**: las pantallas se abren como **UI JSON** (`/api/v1/ui/...`) o **UI nativa** (según `client_open`).
- Dentro de un paso, un motor separado (**`StepIntentEngine`**) interpreta mensajes del usuario **sin cambiar de flujo** y decide qué “sub-acción” (UI) corresponde, a partir de un **manifiesto del paso**.

## Componentes (conceptuales)

- **`IntentEngine` (root)**: clasificación inicial del pedido del usuario para elegir el flujo (p. ej. `turnos.crear-como-paciente`) y devolver un `client_open` para abrir el wizard principal.
- **`Wizard` (cliente)**: mantiene `flow_key`, `step_index`/`step_id`, `draft` y el historial de mensajes.
- **`StepIntentEngine` (por paso)**: corre cuando el wizard ya está activo; recibe el snapshot y el texto y devuelve la intención del **paso** (incluye fallback) limitada por el manifiesto.

## Contrato del wizard (stateless server)

En cada envío de chat en modo wizard, el cliente manda un snapshot:

- `flow_key` (ej. `turnos.crear-como-paciente`)
- `step_id` / `step_index`
- `draft` (valores parciales acumulados)
- `content` (texto libre del usuario, opcional)
- `wizard_message_kind` (ej. `free_text`, `tap`, etc. si se necesita tipar)

El servidor **no guarda** `wizard_session` ni estado entre requests.

## Manifiesto por paso (lo “último conversado”)

### Idea central

El wizard principal se modela como un **esqueleto**. En el **template principal** (UI JSON grande):

- No se busca “meter toda la lógica” en el cliente.
- Cada paso define **qué sub-acciones se pueden ejecutar** (o sugerir) mediante:
  - `allowed_ui_action_ids`: lista de `action_id` (no vacía)
  - `fallback_ui_action_id`: una `action_id` fallback (obligatoria en pasos donde el texto pueda no alcanzar)

**Regla:** el template principal **no puede** quedar con `allowed_ui_action_ids` vacío.

### Qué hace `StepIntentEngine`

En el primer paso (ej. `select_servicio_efector`), ante un mensaje del usuario:

- Analiza el texto **solo dentro del paso**.
- Decide **cuál** `action_id` de `allowed_ui_action_ids` corresponde (o usa fallback).
- Responde con una narrativa y un `client_open`/UI JSON para que el cliente renderice el listado/selector correspondiente.

### Escenarios ejemplificados

#### Escenario A (sin “duda”)

1. Usuario: “necesito un turno para el oftalmólogo”
2. `IntentEngine` (root) elige flujo: `turnos.crear-como-paciente` (abre wizard principal)
3. Paso 0 (`select_servicio_efector`): `StepIntentEngine` detecta la sub-acción (por ejemplo, “listar efectores con el servicio”)
4. Respuesta: “Ok, para comenzar seleccioná un efector del siguiente listado”
5. Cliente abre/renderiza el `client_open` correspondiente (inline/fullscreen según metadata)

#### Escenario B (no puede decidir; fallback)

1. Usuario: “necesito un turno para el oftalmólogo, el que tenga un turno lo más pronto posible”
2. `IntentEngine` (root) abre wizard principal igual (mismo flujo)
3. Paso 0: `StepIntentEngine` no puede mapear “lo más pronto posible” a una sub-acción soportada en MVP
4. Usa `fallback_ui_action_id`
5. Respuesta: “Ok, para comenzar debés seleccionar un efector. No dispongo de información para ordenar por disponibilidad; te paso un listado sin filtro.”

> Nota: no se implementa “más cercano / más rápido” en el plan actual (removido por decisión).

## Sub-UIs (cómo encajan, sin confusión)

- El `action_id` **no incluye** `/ui/`: eso es routing. Ejemplo: `turnos.listar-efectores` → `/api/v1/ui/turnos/listar-efectores`.
- `StepIntentEngine` **no descubre** nada nuevo: **solo** puede devolver acciones del manifiesto del paso.
- El catálogo root (para `IntentEngine`) sigue descubriendo UIs por los mecanismos existentes (templates `views/json/*/*.json` + RBAC). Si una sub-UI debe existir, se implementa como un descriptor `/api/v1/ui/...` normal.

## Qué se considera “hecho” para MVP

- `IntentEngine` abre `turnos.crear-como-paciente`.
- Paso 0 interpreta texto con `StepIntentEngine` usando **solo** `allowed_ui_action_ids` + fallback.
- El cliente renderiza la sub-UI seleccionada y acumula `draft` local.
- El submit final sigue siendo `POST /api/v1/ui/turnos/crear-como-paciente` (negocio en backend).

