# Fase 03b — Encounter en flujos puntuales

## Ejecutado

### Encuesta parches mamarios

- `EncuestaParchesMamariosController::actionCreate` crea `Encounter` vía `EncounterLifecycleService` (AMB, parent `ENCUESTA_PARCHES`) y lo finaliza al guardar.
- `ConsultaAtencionesEnfermeria` y `PersonasAntecedente` referencian `encounter_id` (compat `id_consulta` en PHP).
- Eliminada creación de fila en `consultas`.

### Constantes `Encounter` (sin tocar lógica `Consulta`)

- `Turno`, `PacienteController`, `pacientes/listado.php`: `Consulta::PARENT_*` / `ENCOUNTER_CLASS_*` → `Encounter::`.

### `ConsultaAtencionesEnfermeria`

- Columna `encounter_id` (post `m260520_100001`); getters/setters `id_consulta` para código legacy.
- `getEncounter()`; `syncProfesionalEfectorServicioFromContext` lee PES desde `Encounter`.

## Pendiente (Fase 03c)

- `PacientesController` / motivos: dejar de usar `Consulta::findOne` para última consulta.
- `ConsultaProcesamientoService` / `EncounterDocumentationService` sin escribir `consultas`.
- `PersonasAntecedente.id_consulta` → `encounter_id` (migración BD).
- `Autofacturacion`, `Reporte`, derivaciones, drop `consultas`.
