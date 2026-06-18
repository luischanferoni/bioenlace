# Plan: atención remota y async (por etapas)

Plan de implementación. Al cerrar cada etapa, volcar lo estable a `web/docs/producto/atencion-remota-async.md` y retirar este archivo.

## Objetivo

Introducir atención remota (videollamada con turno) y consulta async (mensaje, sin turno ni video) con adopción gradual: el personal médico y los encargados de efector parten de un modelo 100 % presencial.

## Principios

- Reutilizar triage de reserva (`reserva_triage_*`) y `TeleconsultaElegibilidadService`; no duplicar reglas clínicas en orquestadores.
- La agenda presencial sigue siendo el default; `acepta_consultas_online` es opt-in del profesional.
- Educar antes de obligar: insights informativos, no auditoría individual.
- Diferenciar en producto **videollamada** (turno + `tipo_atencion=teleconsulta`) y **async** (encounter sin cita, chat).

## Etapas

| Etapa | Nombre | Entregable | Estado |
|-------|--------|------------|--------|
| 0 | Observación | Insight en listado de turnos del día (presencial + triage elegible) | Hecho |
| 1 | Oferta paciente | Flow `atencion.necesito-atencion` ofrece remoto cuando política de servicio lo permite; hub si nadie tiene agenda online | Hecho |
| 2 | Opt-in profesional | Capacitación + `acepta_consultas_online`; priorizar async sobre video | Hecho |
| 3 | Bandeja async | Encounter VR sin `appointment_id`, chat, SLA, reparto por servicio | Pendiente |
| 4 | Política por servicio | Métricas AdminEfector, reglas por servicio en metadata | Pendiente |

## Etapa 0 (detalle)

### Backend

- Catálogo declarativo: `Domain/Scheduling/metadata/staff_modalidad_insight.yaml`
- `TurnoReservaTriageDraftBuilder` — reconstruye draft desde `reserva_triage_meta_json` / columnas triage
- `StaffModalidadInsightCatalogService` — textos y modalidades sugeridas por elegibilidad
- `StaffTurnoModalidadInsightService` — insight por turno (null si no aplica)
- `StaffClinicalDayListService` — campo `modalidad_insight` en cada turno del panel

### Frontend web

- Plantilla turno en `_listado_templates.php` + `fillTurnoCard` en `pacientes-listado.js`

### Criterios de visualización

- Turno `tipo_atencion=presencial`
- Triage persistido (`reserva_triage_code` o path en meta)
- Elegibilidad clínica `sugerido` o `permitido` (no `excluido`, no `presencial_preferido`)
- Mensaje informativo; si la agenda del PES no tiene `acepta_consultas_online`, pie opcional sin obligar acción

### Tests

- `StaffTurnoModalidadInsightServiceTest` — draft builder e insight nulo/visible

## Etapa 1 (detalle)

### Backend

- Catálogo `reserva_modalidad_atencion.yaml` — presencial, teleconsulta, async
- `ReservaModalidadAtencionService` — opciones y flags `modalidad_paso_requerido`, `async_ofrecible`
- `ConsultaAsyncSolicitudService` — encounter VR `planned`, parent `SOLICITUD_ASYNC`
- API `GET|POST /api/v1/consulta-async/solicitar-como-paciente`
- Migración RBAC `m260618_120000_api_consulta_async_solicitar_rbac`
- Paso modalidad en `TurnosController` usa el nuevo servicio
- Hub teleconsulta sin cupos: mensaje en `slots-dias-disponibles-como-paciente`

### Flow asistente

- `atencion.necesito-atencion`: salta modalidad si solo presencial; rama `tipo_atencion=async` → `solicitud_async`
- Subintent `solicitud_async` con UI form (POST directo, sin `flow_submit` de turno)

### Criterios async paciente

- Elegibilidad clínica `sugerido` o `permitido` (no requiere `teleconsulta_politica` del servicio)
- Teleconsulta sigue reglas existentes (`TeleconsultaElegibilidadService` + hub)

## Etapa 1 (borrador — archivado)

## Etapa 2 (detalle)

### Backend / metadata

- `agenda_atencion_remota.yaml` — copy capacitación, KPI, link a configurar agenda
- `AgendaAtencionRemotaCatalogService`
- `StaffModalidadInsightMetricsService` — turnos presenciales con triage `sugerido` (30 días)
- `AgendaConfigUiFlowService::enrichAtencionRemotaCopy` — mensaje + label/hint en configurar agenda
- KPI extra en `StaffAgendaKpiSectionProvider` cuando hay casos sugeridos
- Insight turno: `agenda_config` con link al asistente si agenda sin online

### UI

- `pacientes-listado.js` — enlace «Configurar mi agenda» en pie del insight
- Intent `profesional-agenda.configurar-propio` — semántica actualizada

## Etapa 2 (borrador — archivado)

- Copy en configurar agenda
- Dashboard efector: % turnos presenciales con insight `sugerido`

## Etapa 3 (borrador)

- Nuevo parent encounter / flujo asistente solicitud async
- Bandeja staff separada del listado horario

## Referencias

- `web/docs/producto/teleconsulta-elegibilidad.md`
- `web/docs/producto/triage-reserva-turno.md`
- `TeleconsultaElegibilidadService`, `StaffClinicalDayListService`
