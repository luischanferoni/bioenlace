# Agenda y turnos

**Madurez orientativa:** 3,5 / 4 (~88 %)

## Lo que tenemos

- [x] Agenda ambulatoria por profesional–efector–servicio (PES) y cupos reservables (única grilla para pacientes).
- [x] Horario de guardia e internación (presencia), distinto de los turnos; conflicto con la grilla semanal al guardar.
- [x] Autogestión del paciente: reservar, cancelar, reprogramar, confirmar asistencia, según política del efector.
- [x] Triage de motivo y alarmas antes de reservar; banda A no saca turno en la app.
- [x] Teleconsulta en reserva cuando el servicio y el caso lo permiten.
- [x] Turnos en resolución si cambia la agenda; shortlist de horarios; auto-reubicación con opt-in.
- [x] Adelantamiento si se libera un cupo; escalada email/SMS si no hay respuesta al push; cierre de loop sin respuesta.
- [x] Anti no-show por reglas (confirmación extra o liberación de cupo).
- [x] Ruteo si no hay cupo tras el triage (mensaje, tele, primaria, espera).
- [x] Sobreturno y cancelación masiva de un día (staff); alta de turno para terceros.
- [x] Representación: tutor o delegado opera turnos del sujeto.
- [x] Indicadores de acceso (no-show, lead time) para el equipo.
- [x] Espejo de citas con red FHIR (entrante/saliente); desactivado por defecto hasta piloto.

## Lo que falta

- [ ] Lista de espera entre efectores con priorización clínica.
- [ ] Obras sociales / autorizaciones en el mismo flujo de reserva.
- [ ] Slots separados presencial / remoto (hoy comparten grilla).
- [ ] Piloto en producción con datos reales de red nacional de turnos.
- [ ] Panel histórico exportable (CSV/PDF) y comparación entre servicios.
- [ ] Perfil factual persistido (asistencia, no-show, cancelación) separado de preferencias y políticas.

## Documentación de producto

[turnos.md](../producto/turnos.md) · [triage-reserva-turno.md](../producto/triage-reserva-turno.md) · [teleconsulta-elegibilidad.md](../producto/teleconsulta-elegibilidad.md) · [agenda-por-encounter-class.md](../producto/agenda-por-encounter-class.md) · [interoperabilidad-agendamiento-fhir.md](../producto/interoperabilidad-agendamiento-fhir.md) · [agentes-autonomos.md](../producto/agentes-autonomos.md)
