# Sobreturno (turno urgente)

## Comportamiento

- Turno creado con `es_sobreturno = 1` vía `POST turnos/crear-sobreturno` (web, mismo payload que crear turno) o lógica equivalente.
- **No** se aplica el límite de cupo de agenda (turno extra).
- `SobreturnoService::notificarRetrasoPorSobreturno` notifica (push inmediato + fila programada) a pacientes con turno **posterior** el mismo día, mismo `id_rrhh_servicio_asignado`.

## UI

Botón **Sobreturno** en el modal de agenda cuando el día está completo (`todosTomados`) o al elegir horario libre con cupo lleno según `turnos_calendario.js`.

## Configuración

`efector_turnos_config.sobreturno_notificar_retraso` y `sobreturno_minutos_retraso_estimado`.
