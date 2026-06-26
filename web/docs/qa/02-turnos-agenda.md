# Turnos y agenda

[← Índice](./README.md) · Más detalle: [turnos.md](../producto/turnos.md)

Antes conviene tener [sesión operativa](./00-transversal.md#elegir-efector-servicio-y-tipo-de-atención) en ambulatorio.

---

## Ver la agenda del consultorio (personal)

1. **Vos** entrás a Turnos / agenda del efector.
2. **El sistema** muestra los servicios, feriados y, si hay, derivaciones pendientes del paciente que tengas seleccionado.
3. **Vos** elegís día y profesional.
4. **El sistema** muestra los turnos de ese día.

---

## Lista de espera del día

1. **Vos** abrís la lista de espera (menú o desde la agenda).
2. **El sistema** lista quién tiene turno hoy, con datos del paciente.
3. **Vos** cambiás de fecha (ayer / mañana).
4. **El sistema** actualiza la lista.

---

## Sacar un turno (secretaría / personal)

1. **Vos** elegís paciente, profesional, fecha y horario con cupo libre.
2. **El sistema** crea el turno y lo muestra en agenda y lista de espera.
3. Si el horario ya está ocupado, **te avisa** y no lo duplica.

---

## Sacar turno desde la app (paciente)

1. **Vos** (paciente) elegís servicio, profesional y horario disponible.
2. **El sistema** confirma el turno y lo ves en “mis turnos”.
3. Si no hay cupo, **te muestra** otros horarios o te guía en el asistente.
4. Con **contexto paciente** activo, solo ves profesionales/efectores de tu provincia y sector ([08-registro-contexto-paciente.md](./08-registro-contexto-paciente.md)).

---

## Cancelar turno (paciente)

1. **Vos** cancelás con la anticipación que permita la regla del efector.
2. **El sistema** marca el turno cancelado y libera el cupo.
3. Si cancelás muy tarde, **puede** rechazarlo y explicarte el motivo.

---

## Cambiar fecha u hora del turno

1. **Vos** (paciente o personal, según permiso) pedís reprogramar.
2. **El sistema** ofrece nuevos cupos o te guía en el asistente.
3. Al confirmar, **actualiza** el turno y el cupo viejo queda libre.

---

## Ver derivaciones pendientes

1. **Vos** entrás a Referencias (o el listado que use el efector).
2. **El sistema** muestra derivaciones hechas en consulta que aún no tienen turno.
3. **Vos** elegís una y programás turno.
4. **El sistema** marca la derivación como con turno asignado.

---

## Sobreturno

1. **Vos** (personal con permiso) cargás un turno fuera del cupo normal.
2. **El sistema** lo guarda marcado como sobreturno y aparece en la agenda del día.

---

## Marcar “no se presentó”

1. **Vos** indicás que el paciente no vino.
2. **El sistema** cambia el estado del turno y el cupo puede quedar registrado como ausencia.

---

## Calendario del profesional

1. **Vos** ves el calendario de un profesional (día o semana).
2. **El sistema** pinta turnos, bloqueos y feriados.

---

## Indicadores de agenda (ocupación, etc.)

1. **Vos** pedís en el asistente o en la pantalla de indicadores algo como “cómo va la agenda hoy”.
2. **El sistema** responde con números o resumen según lo configurado.

---

## Conflicto de agenda o cancelación masiva

1. **Vos** (coordinación) iniciás el flujo de resolver conflictos o cancelar varios turnos.
2. **El sistema** te va preguntando qué turnos afectar y confirma antes de aplicar.
3. Al terminar, **los pacientes afectados** pueden recibir aviso si está activo el envío.

---

## Confirmar que vas a ir (paciente)

1. **Vos** confirmás asistencia al turno desde la app o el asistente.
2. **El sistema** registra la confirmación para el personal.

---

## Aviso al celular cuando cambia un turno

1. Si tenés la app con notificaciones, **el sistema** te manda push cuando te reprograman o cancelan un turno (cuando el efecto lo tenga configurado).
