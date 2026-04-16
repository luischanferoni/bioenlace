---
name: step-intent-engine
overview: Actualizar el diseño hacia SubIntents + views embebibles granulares por `action_id`, sin wizard monolítico como skeleton y sin retrocompatibilidad. El YAML por intent es fuente de verdad de conversación (pasos, dependencias, confirmación, normalización) y el catálogo `/api/v1/...` + RBAC sigue siendo fuente de verdad de acciones UI disponibles.
todos:
  - id: yaml-intents-and-globals
    content: Definir YAML por `intent_id` (action_id) y YAML globales/comunes (p. ej. adquisición de ubicación, normalización temporal, política de confirmación) en `schemas/` (carpeta nueva).
    status: completed
  - id: picker-contract
    content: Estandarizar contrato v1 de views embebibles (chips + listado tipo “picker” + confirmación obligatoria + draft_delta) y cómo disparan custom_widgets (inline/fullscreen).
    status: completed
  - id: picker-intents-mvp
    content: "Crear views embebibles (templates UI JSON + permisos RBAC) para el flujo `turnos.crear-como-paciente`: `servicios.elegir`, `efectores.elegir`, `efectores.elegir-nearby`, `rrhh.elegir`, `turnos.elegir-slot`."
    status: completed
  - id: subintent-engine
    content: "Implementar `SubIntentEngine` (por intent_id) que consume YAML del intent + snapshot (draft + step/subintent actual + mensaje/interaction) y decide: pregunta, `open_ui(action_id, params)`, o `draft_delta`. No persistir estado en servidor."
    status: completed
  - id: assistant-contract-break
    content: "Redefinir contrato único de `POST /api/v1/asistente/enviar` (sin legacy): soportar texto + interacciones (confirm_selection, chip_select, etc.), devolver `actions/client_open` + `draft_delta` + narrativa."
    status: completed
  - id: clients-web-flutter
    content: "Web + Flutter: renderizar views embebibles dentro del chat (burbuja/card), manejar confirmación obligatoria, y abrir custom_widgets inline/fullscreen según metadata."
    status: completed
isProject: false
---

## Plan SubIntentEngine + views embebibles (actualizado)

### 1) Alcance y separación con IntentEngine

- **IntentEngine (root)**:
  - Sigue siendo el **clasificador inicial** (texto → `action_id` permitido) usando el catálogo `/api/v1/...` filtrado por RBAC.
  - No conoce reglas conversacionales; solo elige “qué UI/intent abrir” inicialmente.

- **SubIntentEngine (dentro del intent)** — foco de este plan:
  - Motor conversacional por `intent_id` (action_id) que orquesta subintents/pasos, dependencias, confirmaciones y apertura de views embebibles.
  - Consume YAML por intent + YAML globales (ubicación/tiempo/confirmación).
  - Abre views embebibles por `action_id` (ej. `efectores.elegir`) y el catálogo resuelve `route/client_open`.

### 2) Modelo de datos: YAML por intent + YAML globales

- **Objetivo**: que el motor conversacional no tenga reglas hardcodeadas; todo lo declarativo vive en YAML.
- **Por intent** (ej. `turnos.crear-como-paciente`):
 - **Por intent** (ej. `turnos.crear-como-paciente`):
  - YAML que define:
    - `subintents[]` (opcional): pasos conversacionales (puede ser 0, 1 o N).
    - `requires/provides` por subintent (dependencias de draft).
    - políticas: confirmación obligatoria, “cualquiera”, “más pronto”, cercanía, etc.
    - normalización de lenguaje (mañana/siesta/cerca de casa) y fallbacks.
- **Globales/comunes** (en `schemas/`):
  - adquisición de ubicación (A/B/C), normalización temporal, política de confirmación, etc.
- **Validación**:
  - el loader valida que `action_id` referidos existan en el catálogo (templates UI JSON + RBAC) y que no haya subintents sin salida/fallback.

### 3) Catálogo de UIs (action_id → view concreta)

- Se mantiene el catálogo existente: templates JSON bajo `/api/v1/<entity>/<action>` + RBAC.
- Cada view embebible es una **acción UI individual** (`<entidad>.<accion>`) con su template, keywords y permiso.
- El motor conversacional solo referencia `action_id`; la resolución a `route/client_open` la hace el catálogo existente.

### 4) Lógica de SubIntentEngine (por intent)

- Entrada:
  - `intent_id` actual, `subintent_id` (si hay), `draft`, y el último `content` o `interaction`.
  - YAML del intent + globales importados.
- Proceso:
  - Determinar requirements faltantes del subintent actual.
  - Si falta algo: preguntar o abrir SubUI/picker para obtenerlo.
  - Si hay selección de item: pedir confirmación obligatoria y aplicar `draft_delta` solo al confirmar.
  - Emitir `open_ui(action_id, params)` para abrir views embebibles (ej. `efectores.elegir`) o custom_widget.
- Salida:
  - Narrativa de asistente (texto).
  - `draft_delta` (solo cambios confirmados).
  - `question` o `actions/client_open` para abrir picker/custom_widget.

### 5) Views embebibles + patrón de embebido

- Las views embebibles se renderizan dentro del chat como cards/burbujas.
- Metadatos determinan:
  - chips/filters
  - confirmación obligatoria
  - `custom_widget` inline/fullscreen (por requirements como ubicación)

### 6) Aplicación al primer caso: `turnos.crear-como-paciente`

- Subintents MVP:
  - `select_servicio` -> abre `servicios.elegir`
  - `select_efector` -> abre `efectores.elegir` o `efectores.elegir-nearby` (si “cerca/cercanos”)
  - `select_rrhh` -> abre `rrhh.elegir`
  - `select_slot` -> abre `turnos.elegir-slot`
  - `confirm` -> submit de negocio de turnos (API)
- Regla: cada selección de ítem requiere confirmación antes de aplicar `draft_delta`.

### 7) Entregables del plan

- Schemas YAML (globales + por intent) bajo `schemas/`.
- Contrato v1 de views embebibles (UI JSON).
- MVP de views embebibles creadas como acciones UI (templates + RBAC).
- SubIntentEngine v1 (interpretación + confirmación + open_ui).
- Clientes web + Flutter actualizados al contrato único del asistente (sin legacy).

