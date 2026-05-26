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

## Lo que falta

- [ ] Lista de espera nacional entre efectores con priorización clínica.
- [ ] Integración con obras sociales / autorizaciones en el mismo flujo de reserva.
- [ ] Teleconsulta como modalidad first-class en agenda (donde no esté ya configurado).
- [ ] Panel histórico exportable (CSV/PDF) y benchmarks entre servicios del efector.

## En producto hoy

[producto/turnos.md](../producto/turnos.md)
