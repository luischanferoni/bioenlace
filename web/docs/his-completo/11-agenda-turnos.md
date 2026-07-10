# Agenda y turnos

**Madurez orientativa:** 3,25 / 4 (~81 %)

## Lo que tenemos

- [x] Agenda **AMB** por profesional–efector–servicio (PES) y cupos reservables (única expuesta a pacientes).
- [x] **Cobertura EMER/IMP** (`profesional_cobertura`): roster entrada/salida; conflictos por solape; no genera turnos paciente. Ver [agenda-por-encounter-class.md](../producto/agenda-por-encounter-class.md).
- [x] Autogestión del paciente: reservar, cancelar, reprogramar según política del efector (**solo AMB**).
- [x] Turnos en conflicto / en resolución tras cambios de agenda AMB (staff y paciente).
- [x] Sobreturno y cancelación masiva de día (staff).
- [x] Notificaciones push (recordatorios, cambios, resumen de atención listo, etc.).
- [x] Alta de turno por staff para terceros.
- [x] Acciones guiadas por conversación para flujos de turnos.
- [x] **Métricas de acceso (staff):** no-show (`SIN_ATENDER` por paciente), tasa sobre cerrados y mediana/promedio de días reserva → cita (`/api/v1/turnos/indicadores-agenda`, intent `turnos.indicadores-agenda-flow`).
- [x] **Espejo NIS FHIR (v1):** pull `Appointment` → `turnos`, onboarding Schedule → PES, push de estados salientes — desactivado por defecto; ver [interoperabilidad-agendamiento-fhir.md](../producto/interoperabilidad-agendamiento-fhir.md).

## Lo que falta

- [x] **Teleconsulta en reserva:** política por servicio, elegibilidad por triage, modalidad condicional en `atencion.necesito-atencion`, `acepta_consultas_online` en agenda PES, badge en app Personal de Salud. Ver [teleconsulta-elegibilidad.md](../producto/teleconsulta-elegibilidad.md).
- [ ] Cruce de conflictos cobertura vs grilla AMB (licencias / indisponibilidad unificada).
- [x] Conflicto cobertura vs cupos AMB al guardar roster (`cobertura_vs_amb_slots`).
- [x] Plantel activo en panel EMER/IMP (`staff_cobertura_activa`).
- [ ] Lista de espera nacional entre efectores con priorización clínica.
- [ ] Integración con obras sociales / autorizaciones en el mismo flujo de reserva.
- [ ] Piloto en producción NIS MSAL (datos reales + cron habilitado).
- [ ] Slots diferenciados presencial/remoto (hoy comparten grilla).
- [ ] Panel histórico exportable (CSV/PDF) y benchmarks entre servicios del efector.

## En producto hoy

- API cupos: `GET /api/v1/turnos/indicadores-agenda`
- API cobertura: `/api/v1/profesional-cobertura/*`
- Asistente: `turnos.indicadores-agenda-flow`, `profesional-cobertura.gestionar-*`
- Historia: [turnos.md](../producto/turnos.md), [agenda-por-encounter-class.md](../producto/agenda-por-encounter-class.md)
