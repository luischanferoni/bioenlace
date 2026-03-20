---
name: quirofano-niveles
overview: Alinear Quirófano con Nivel 1 (Consulta clínica) para “informe de lo ocurrido” y Nivel 2 (agenda quirúrgica) solo para agendar cirugía, usando persistencia real via `ConsultaController::actionGuardar` y anclando la Consulta al `parent=CIRUGIA`.
todos:
  - id: consulta-cirugia-parent
    content: Agregar soporte `parent=CIRUGIA` en `common/models/Consulta.php` (constante, PARENT_CLASSES y relación).
    status: pending
  - id: validar-permiso-cirugia-parent
    content: Extender `common/models/ConsultasConfiguracion.php::validarPermisoAtencion()` para rama `PARENT_CIRUGIA` (validar Cirugia y setear `idServicio`/`encounterClass`).
    status: pending
  - id: api-informe-quirofano
    content: Agregar endpoint en `frontend/modules/api/v1/controllers/QuirofanoController.php` para obtener la Consulta del informe clínico asociado a `cirugia_id`.
    status: pending
  - id: create-quirofano-nivel1
    content: "Actualizar `frontend/views/quirofano/create_cirugia.php`: crear agenda (Nivel2) sin persistir informe en `Cirugia`, y persistir informe en `consulta/guardar` con `parent=CIRUGIA`."
    status: pending
  - id: update-quirofano-nivel1
    content: "Actualizar `frontend/views/quirofano/update_cirugia.php`: cargar `qc-proc`/`qc-obs` desde la Consulta asociada y guardar informe en Nivel1 (sin escribir en `Cirugia`)."
    status: pending
  - id: partial-form-consulta-parent-hidden
    content: Asegurar que el formulario de `consulta/guardar` reciba `parent`/`parent_id` (via partial o hidden fields).
    status: pending
  - id: compat-migracion
    content: Definir estrategia de compatibilidad/migración para Cirugias existentes con `procedimiento_descripcion/observaciones`.
    status: pending
isProject: false
---

## Objetivo

Modificar el módulo de Quirófano para que:

- **Agendar cirugía** sea **Nivel 2** (agenda) y persista en `Cirugia`.
- **“Redactar lo que pasó” / informe quirúrgico (clínico)** sea **Nivel 1** y persista vía `POST /api/v1/consulta/guardar` (`ConsultaProcesamientoService::guardar`).
- La **Consulta** creada quede **anclada a la Cirugía** mediante `parent=CIRUGIA` y `parent_id=<id_cirugia>` (similar a cómo se ancla por `Turno`).
- En `update_cirugia`, `qc-proc`/`qc-obs` se **pueblan desde la Consulta asociada**, no desde `Cirugia.procedimiento_descripcion/observaciones`.

## Diagrama (flujo deseado)

```mermaid
flowchart TD
 A[UI Quirófano / create/update] --> B[Guardar agenda (Nivel2)]
 B --> C[POST /api/v1/quirofano/cirugias (solo campos de agenda)]
 C --> D[Cargar/crear informe clínico (Nivel1)]
 D --> E[POST /api/v1/consulta/analizar (opcional, para IA)]
 E --> F[POST /api/v1/consulta/guardar (Nivel1)]
 F --> G[Consulta guardada: parent=CIRUGIA, parent_id=cirugia_id]
```



## Cambios backend (necesarios)

1. **Soportar `parent_class` = `CIRUGIA` en el dominio de Consulta**
  - Archivo: `web/common/models/Consulta.php`
  - Tareas:
    - Agregar constante `PARENT_CIRUGIA`.
    - Incluir `PARENT_CIRUGIA` en `PARENT_CLASSES` mapeando a `\common\models\Cirugia`.
    - Ajustar/expandir el método de relación de parent (hoy `getParent()` está incompleto/TODO y actualmente hardcodea `Turno`).
2. **Hacer que `ConsultasConfiguracion::validarPermisoAtencion()` entienda `parent=CIRUGIA`**
  - Archivo: `web/common/models/ConsultasConfiguracion.php`
  - Tareas:
    - Añadir rama para `if ($parent == Consulta::PARENT_CIRUGIA)`.
    - Validar que la `Cirugia` exista, pertenezca al paciente y (si aplica) al efector.
    - Definir `encounterClass` e `idServicio` para que `ConsultaProcesamientoService::guardar()` pueda resolver `id_configuracion` correctamente.
3. **API en Quirófano para cargar el informe clínico existente**
  - Archivo: `web/frontend/modules/api/v1/controllers/QuirofanoController.php`
  - Nuevo endpoint recomendado:
    - `GET /api/v1/quirofano/cirugias/<id>/informe-clinico`
  - Respuesta esperada (ejemplo):
    - `{ success, data: { id_consulta, id_configuracion, texto_original, texto_procesado/observacion } }`
  - Tareas:
    - Buscar la `Consulta` por `parent_class = Consulta::PARENT_CLASSES[PARENT_CIRUGIA]` y `parent_id = <id>`.
4. (Opcional, según UX) Endpoint para “guardar informe” sin UI de IA
  - Si querés mantener “un solo botón” sin modal de `chat-inteligente`, crear endpoint tipo:
    - `POST /api/v1/quirofano/cirugias/<id>/informe-clinico`
  - Este endpoint orquesta `ConsultaProcesamientoService` (analizar + guardar) y recibe el texto ya armado por el front.

## Cambios frontend (Quirófano)

1. **create cirugia: separar agenda (Nivel2) de informe clínico (Nivel1)**
  - Archivo: `web/frontend/views/quirofano/create_cirugia.php`
  - Tareas:
    - En el POST a `/api/v1/quirofano/cirugias` **NO enviar** `procedimiento_descripcion` ni `observaciones`.
    - Conservar `qc-proc`/`qc-obs` como texto de **informe clínico**.
    - Después de crear la cirugía (tener `cirugia_id`), abrir UI para informe clínico (reutilizar el `chat-inteligente.js`/modal de consulta existente) o llamar al endpoint de guardar informe (si se implementa).
    - Al enviar a `consulta/guardar`, incluir hidden:
      - `parent = 'CIRUGIA'`
      - `parent_id = <cirugia_id>`
      - `id_persona = qc-persona`
      - `id_configuracion` (si el flujo UI/servicio lo necesita explícitamente; si no, dejar que el service lo resuelva desde DB según contexto).
2. **update cirugia: cargar `qc-proc`/`qc-obs` desde la Consulta asociada**
  - Archivo: `web/frontend/views/quirofano/update_cirugia.php`
  - Tareas:
    - Actualizar `applyCirugia()` para que:
      - Las textareas `qu-proc`/`qu-obs` se rellenen desde la respuesta del endpoint `GET .../informe-clinico`.
      - Si existe `id_consulta`, guardarlo en un hidden para que `ConsultaProcesamientoService::guardar()` haga update (si así lo querés), en vez de crear duplicados.
    - Cambiar el PATCH a `/api/v1/quirofano/cirugias/<id>` para que **NO persista** procedimiento/observaciones en `Cirugia`.
    - Añadir acción “Guardar informe clínico” que persista en `consulta/guardar` (Nivel1) con `parent=CIRUGIA`.
3. **Extender el formulario de consulta (si hace falta) para incluir parent hidden**
  - Archivo sugerido: `web/frontend/views/paciente/_formulario_consulta.php`
  - Tareas:
    - Permitir inyectar `parent` y `parent_id` al renderizar el formulario dentro del contexto quirófano.
    - Si no se quiere tocar el partial genérico, crear un partial nuevo `*_formulario_consulta_quirofano.php`.

## Migración / compatibilidad de datos

1. **Qué hacer con `Cirugia.procedimiento_descripcion/observaciones` ya existentes**
  - Estrategias a definir:
    - Mantenerlas (solo lectura) y no escribir nuevas.
    - O crear un script job para migrar a `Consulta` y, opcionalmente, limpiar campos viejos.

## Criterios de aceptación

- Al guardar un informe quirúrgico desde Quirófano:
  - Se crea/actualiza una `Consulta` con `parent_class=CIRUGIA` y `parent_id=<cirugia_id>`.
  - El texto clínico persiste por `ConsultaProcesamientoService::guardar()` y genera las categorías/modelos correspondientes.
  - `Cirugia` queda como agenda (sin duplicar el informe en `procedimiento_descripcion/observaciones`).
- Al abrir `update_cirugia`, `qc-proc`/`qc-obs` se cargan desde la `Consulta` y no desde los campos de `Cirugia`.

## Archivos clave a tocar

- `web/common/models/Consulta.php`
- `web/common/models/ConsultasConfiguracion.php`
- `web/frontend/modules/api/v1/controllers/QuirofanoController.php`
- `web/frontend/views/quirofano/create_cirugia.php`
- `web/frontend/views/quirofano/update_cirugia.php`
- `web/frontend/views/paciente/_formulario_consulta.php` (o nuevo partial)

