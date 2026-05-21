# Sobreturno (turno urgente)

## Objetivo

Permitir un turno extra fuera de cupo con aviso de retraso estimado a pacientes del mismo PES y día.

## Actores

- Staff de agenda (creación).
- Pacientes con turnos posteriores el mismo día (notificación).

## Anclas

| Paso | Componente |
|------|------------|
| Alta | `POST …/turnos/crear-sobreturno` |
| Notificación | `SobreturnoService::notificarRetrasoPorSobreturno` |
| Config | `efector_turnos_config` (sobreturno_*) |

## Comportamiento

- Turno creado con `es_sobreturno = 1` vía `POST turnos/crear-sobreturno` (web, mismo payload que crear turno) o lógica equivalente.
- **No** se aplica el límite de cupo de agenda (turno extra).
- `SobreturnoService::notificarRetrasoPorSobreturno` notifica (push inmediato + fila programada) a pacientes con turno **posterior** el mismo día, mismo **`id_profesional_efector_servicio`** (PES).

## UI

Botón **Sobreturno** en el modal de agenda cuando el día está completo (`todosTomados`) o al elegir horario libre con cupo lleno según `turnos_calendario.js`.

## Configuración

`efector_turnos_config.sobreturno_notificar_retraso` y `sobreturno_minutos_retraso_estimado`.
