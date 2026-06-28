# Turnos y agenda (médico)

[← Médico](./README.md) · Staff (secretaría, indicadores): [staff/turnos-agenda.md](../staff/turnos-agenda.md)

Antes conviene tener [sesión operativa](../staff/transversal.md#elegir-efector-servicio-y-tipo-de-atención) en **ambulatorio**.

---

## Web — Agenda del día (asistente)

**Intent:** `turnos.ver-agenda-dia-profesional-flow`

1. **Vos** abrís **Asistente** y escribís *«agenda del día»*, *«turnos de hoy»* o *«pacientes hoy»*.
2. **El sistema** pide la fecha (y profesional si no estás en contexto PES).
3. **El sistema** muestra el listado de turnos/citas de ese día.

---

## Web — Panel Pacientes del día

Equivalente operativo al panel **Pacientes del día** (consultorio).

1. **Vos** entrás con sesión en **ambulatorio** y abrís **Pacientes** en la barra lateral.
2. **El sistema** muestra el panel **Pacientes del día**.
3. **Vos** abrís un turno para atender → captura clínica ([captura-clinica.md](./captura-clinica.md)).

---

## Web — Calendario y horarios propios

**Intent (ver citas del día):** `turnos.ver-agenda-dia-profesional-flow`

**Intent (ocupación / slots):** `turnos.consultar-ocupacion-dia-flow`

**Intent (configurar mi agenda):** `profesional-agenda.configurar-propio` — Atajo **Profesional, agenda y condición laboral** → **Para mí**

1. **Vos** elegís ver el día, consultar ocupación o abrir la configuración de horarios propios.
2. **El sistema** muestra la grilla, los turnos del día o el formulario de horarios.
3. **Vos** revisás o guardás cambios si estás configurando.
4. **El sistema** refleja turnos, bloqueos y feriados en la agenda.

---

## Web — Programar turno desde una derivación

Tras registrar una derivación en [captura-clinica.md](./captura-clinica.md). Coordinación staff suele usar `turnos.crear-para-paciente-flow` — ver [staff/turnos-agenda.md](../staff/turnos-agenda.md).
