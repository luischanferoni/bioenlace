# Fase 3 — UI paciente y asistente

## Objetivo

Descubrimiento y lectura del resumen en app y asistente (patrón laboratorio/receta).

## Checklist

- [x] UI JSON: `mis-atenciones-como-paciente`, `ver-resumen-atencion-como-paciente`, `ultima-atencion-ui-como-paciente`
- [x] JSON API: `listar-atenciones-como-paciente`, `ver-resumen-como-paciente`, `ultima-atencion-como-paciente`
- [x] `ClinicalUiActionCatalog` + intents YAML:
  - `atencion.ver-ultima-como-paciente`
  - `atencion.mis-atenciones-como-paciente`
- [x] Categoría en `CommonActionsService` (“Mis atenciones”)
- [x] RBAC UI: `m260601_100002_api_encounter_patient_summary_ui_rbac.php`
- [x] Flutter: lista + detalle nativo; handler push `ENCOUNTER_SUMMARY_READY` → detalle
- [x] `NativeScreenRouter`: `encounter_summary_list`
- [x] Acceso desde Inicio (tarjeta “Mis atenciones”)
- [x] Render `narrativeText` (texto plano con saltos de línea)

## Criterio de cierre

Paciente abre desde push, asistente o menú y ve texto IA + cabecera de la atención.
