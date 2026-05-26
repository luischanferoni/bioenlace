# Fase 01 — Eliminación segura y candidatos fuertes

## Objetivo

Quitar código **sin referencias** o **reemplazado por API** documentada, sin tocar aún modelos `Consulta` ni tablas clínicas legacy masivas.

## Eliminado en esta fase

### API

- `frontend/modules/api/v1/controllers/ConsultaController.php`
- Rutas en `frontend/config/main.php`: `consulta/analizar`, `consulta/guardar`

### Vistas site (home antiguo)

- `frontend/views/site/_boxesHome.php`
- `_boxAtencion.php`, `_boxGuardia.php`, `_boxInternacion.php`
- `_boxBusquedaPersona.php`, `_boxNovedades.php`

### Controllers + vistas

- `RecetaController`, `views/receta/`
- `CovidEntrevistaTelefonicaController`, `views/covid-entrevista-telefonica/`
- `GuardiaController`, `views/guardia/`

### Ajustes

- `layouts/produccion/main.php`: ítem menú «Consultas» `/consultas`
- `pacientes/_listado_templates.php`: botón a `guardia/index`
- `backend/views/personas/view.php`: card Covid-19

## Explícitamente no tocado

- `common/models/Guardia.php`, tabla `guardia`, `EmergencyGuardiaController`
- `common/models/Covid*.php` (pendiente migración BD)
- `PacienteController`, `InternacionController`, `SiteController::actionPacientes`

## Criterios de aceptación

1. No hay rutas activas a `guardia/*` MVC en vistas en uso.
2. Clientes usan solo `clinical/encounter/*` para analizar/guardar (no `consulta/*`).
3. `PROGRESS.md` actualizado con `[x]` en ítems de esta fase.
