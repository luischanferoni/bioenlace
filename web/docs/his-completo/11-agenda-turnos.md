# Agenda y turnos

**Madurez orientativa:** 3,25 / 4 (~81 %)

## Lo que tenemos

- [x] Agenda por profesional–efector–servicio (PES) y cupos reservables.
- [x] Autogestión del paciente: reservar, cancelar, reprogramar según política del efector.
- [x] Turnos en conflicto / en resolución tras cambios de agenda (staff y paciente).
- [x] Sobreturno y cancelación masiva de día (staff).
- [x] Notificaciones push (recordatorios, cambios, resumen de atención listo, etc.).
- [x] Alta de turno por staff para terceros.
- [x] Acciones guiadas por conversación para flujos de turnos.
- [x] **Métricas de acceso (staff):** no-show (`SIN_ATENDER` por paciente), tasa sobre cerrados y mediana/promedio de días reserva → cita (`/api/v1/turnos/indicadores-agenda`, intent `turnos.indicadores-agenda-flow`).
- [x] **Espejo NIS FHIR (v1):** pull `Appointment` → `turnos`, onboarding Schedule → PES, push de estados salientes — desactivado por defecto; ver [interoperabilidad-agendamiento-fhir.md](../producto/interoperabilidad-agendamiento-fhir.md).

## Lo que falta

- [x] **Teleconsulta en reserva:** política por servicio, elegibilidad por triage, modalidad condicional en `atencion.necesito-atencion`, `acepta_consultas_online` en agenda PES, badge en app Personal de Salud. Ver [teleconsulta-elegibilidad.md](../producto/teleconsulta-elegibilidad.md).
- [ ] Lista de espera nacional entre efectores con priorización clínica.
- [ ] Integración con obras sociales / autorizaciones en el mismo flujo de reserva.
- [ ] Piloto en producción NIS MSAL (datos reales + cron habilitado).
- [ ] Slots diferenciados presencial/remoto (hoy comparten grilla).
- [ ] Panel histórico exportable (CSV/PDF) y benchmarks entre servicios del efector.

## En producto hoy

- API: `GET /api/v1/turnos/indicadores-agenda`
- Asistente: intent `turnos.indicadores-agenda-flow`
- Historia de producto: [turnos.md](../producto/turnos.md)
