# Fase 12 — Auditoría código muerto (post-retirada MVC consulta)

**Fecha:** 2026-05-20

## Eliminado en esta pasada

| Artefacto | Motivo |
|-----------|--------|
| `frontend/views/paciente/timeline/_timeline_*.php` (9 partials) | Ningún `render()`; timeline usa API `historia-clinica` + `formulario-consulta` |
| `frontend/views/paciente/timeline/_botones.php` | Sin `render()`; `$urlConsulta` indefinido |
| `frontend/filters/SisseConsultaFilter.php` | Solo lo usaba `ConsultaAtencionesEnfermeriaController` (borrado) |
| Modales `#modal-consulta` / `#modal_detail_consulta` | Sin `consultas.js`; atenciones enfermería apuntaba a `#modal-consulta-label` inexistente |

## Muerto pero conservado (deuda explícita)

| Código | Notas |
|--------|--------|
| `Consulta::getModeloConsulta()` | Sin llamadas en el repo; wizard MVC por pasos. No borrar hasta confirmar que ningún script externo lo invoca. |
| `Turno::footerTimeline()`, `Guardia::footerTimeline()`, `SegNivelInternacion::footerTimeline()` | Solo los partials eliminados los invocaban; lógica duplicada con `PatientHistoriaUrl` + listado SPA. Candidatos a borrar en limpieza `common/`. |
| `ConsultasConfiguracion::getUrlPorIdConfiguracion()` | Usado solo desde `getModeloConsulta` (muerto). `validarPermisoAtencion` sigue activo vía `PacienteController`. |
| AR `Consulta*` en `common/models` | Informes (`ReporteController`, `ConsultaBusqueda`), autofacturación, API legacy (`Consulta::findOne`), `EncuestaParchesMamarios`. Fuera de alcance fase 12 web. |
| `consulta-atenciones-enfermeria/*` vistas | Activas vía `AtencionesEnfermeriaController`; dominio distinto del MVC `Consulta*` retirado. |

## Activo (no es muerto)

| Pieza | Uso |
|-------|-----|
| `paciente/timeline/timeline.php` | `PacienteController::actionHistoria` + API |
| `paciente/_formulario_consulta.php` | POST `clinical/encounter/guardar` |
| `PatientHistoriaUrl` | Turno/Guardia (por si se reintroduce timeline PHP) |
| `EncounterDefinitionWorkflowSanitizer` | `workflow_json` + migración m260521_100008 |
| API `MotivosConsultaController`, `ConsultaChatController` | Canal actual |

## Siguiente limpieza opcional

1. Borrar `footerTimeline` en `Turno`, `Guardia`, `SegNivelInternacion`.
2. Deprecar o extraer `getModeloConsulta` a `Clinical/Legacy/` con `@deprecated`.
3. Revisar `ReporteController` / `ConsultaBusqueda` vs datos en `encounter`.
