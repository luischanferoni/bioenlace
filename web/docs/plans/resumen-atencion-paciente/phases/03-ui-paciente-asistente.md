# Fase 3 — UI paciente y asistente

## Objetivo

Descubrimiento y lectura del resumen en app y asistente (patrón laboratorio/receta).

## Checklist

- [ ] UI JSON: `encounter/listar-atenciones-como-paciente`, `encounter/ver-resumen-como-paciente`
- [ ] `ClinicalUiActionCatalog` + intents YAML:
  - `atencion.ver-ultima-como-paciente`
  - `atencion.listar-atenciones-como-paciente` (o `atencion.mis-atenciones-como-paciente`)
- [ ] Categoría en `CommonActionsService` (ej. “Mi salud” / “Atenciones”)
- [ ] Flutter: pantalla detalle resumen; lista atenciones; handler push → detalle
- [ ] `NativeScreenRouter` o ruta dedicada si aplica
- [ ] Render `narrativeText` (markdown o texto plano con saltos de línea)

## Criterio de cierre

Paciente abre desde push, asistente o menú y ve texto IA + cabecera de la atención.
