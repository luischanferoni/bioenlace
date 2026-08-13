# Turnos y agenda (staff web)

[← Staff](./README.md) · Más detalle: [turnos.md](../../producto/turnos.md) · Frases: [asistente.md](./asistente.md)

Antes conviene tener [sesión operativa](./transversal.md#elegir-efector-servicio-y-tipo-de-atención) en **ambulatorio**.

Turnos paciente (app): [paciente/turnos.md](../paciente/turnos.md).

---

## Web — Lista de espera del día (panel Pacientes)

1. **Vos** (personal) entrás con sesión en **ambulatorio** y abrís **Pacientes** en la barra lateral.
2. **El sistema** muestra el panel **Pacientes del día** (turnos de la fecha con datos del paciente; KPIs de agenda si aplican).
3. **Vos** cambiás la fecha si hace falta (ayer / mañana / otra).
4. **El sistema** actualiza la lista de turnos de ese día.

---

## Web — Agenda del día (asistente)

**Intent:** `turnos.ver-agenda-dia-profesional-flow`

1. **Vos** abrís **Asistente** y escribís *«agenda del día»*, *«turnos de hoy»* o *«pacientes hoy»*.
2. **El sistema** pide la fecha (y profesional si no estás en contexto PES).
3. **El sistema** muestra el listado de turnos/citas de ese día.

---

## Web — Sacar turno (secretaría / personal)

**Intent:** `turnos.crear-para-paciente-flow`

1. **Vos** abrís **Asistente** y escribís *«dar turno»*, *«turno para el paciente»* o *«crear turno paciente»*.
2. **El sistema** guía el flujo: paciente, servicio, profesional, fecha y horario libre.
3. **Vos** confirmás.
4. **El sistema** crea el turno; aparece en el panel **Pacientes del día** y en la agenda del profesional.

**Error — mismo horario ocupado**

1. **Vos** intentás reservar un cupo ya tomado.
2. **El sistema** rechaza con mensaje claro; no duplica el turno.

---

## Web — Cancelar turno de un paciente

**Intent:** `turnos.cancelar-para-paciente-flow`

1. **Vos** abrís **Asistente** y pedís cancelar el turno de un paciente.
2. **El sistema** pide el turno y motivo opcional.
3. **Vos** confirmás.
4. **El sistema** cancela con permiso staff; el cupo queda libre.

---

## Web — Reprogramar turno en resolución

**Intent (conflicto por cambio de agenda):** `turnos.conflicto-agenda-flow`

1. **Vos** abrís **Asistente** cuando un turno quedó **en resolución** por cambio de agenda.
2. **El sistema** muestra el turno afectado y cupos alternativos.
3. **Vos** elegís y confirmás (o cancelás).
4. **El sistema** cierra la resolución.

---

## Web — Programar turno desde una derivación

Tras registrar una derivación en [medico/captura-clinica.md](../medico/captura-clinica.md).

**Intent:** `turnos.crear-para-paciente-flow`

1. **Vos** tenés al paciente en contexto y abrís **Asistente** para crear turno.
2. **El sistema** muestra el flujo de alta; si hay derivación pendiente, la tenés en cuenta al elegir servicio y profesional.
3. **Vos** confirmás el turno.
4. **El sistema** marca la derivación como con turno asignado.

---

## Web — Sobreturno

**Intent:** `turnos.crear-sobreturno-flow` — *«sobreturno»*

1. **Vos** abrís **Asistente** y escribís *«sobreturno»*.
2. **El sistema** pide paciente, fecha, hora y servicio.
3. **Vos** confirmás.
4. **El sistema** guarda el turno como sobreturno en la agenda del día.

---

## Web — Marcar «no se presentó»

**Intent:** `turnos.no-se-presento-flow` — *«no vino»*, *«ausente»*

1. **Vos** abrís **Asistente** o marcás desde el turno en el panel.
2. **El sistema** pide identificar el turno.
3. **Vos** confirmás la inasistencia.
4. **El sistema** actualiza el estado del turno (ausencia).

---

## Web — Calendario y horarios del profesional

**Intent (ocupación / slots):** `turnos.consultar-ocupacion-dia-flow`

**Intent (configurar agenda propia):** `profesional-agenda.configurar-propio`

**Intent (configurar agenda de otro):** `profesional-agenda.configurar-staff` — Atajo **Profesional, agenda…** → **Para el personal**

1. **Vos** elegís consultar ocupación o abrir la configuración de horarios (propio o de staff).
2. **El sistema** muestra la grilla, los turnos del día o el formulario de horarios.
3. **Vos** revisás o guardás cambios.
4. **El sistema** refleja turnos, bloqueos y feriados en la agenda.

---

## Web — Indicadores de agenda

**Intent:** `turnos.indicadores-agenda-flow` — Atajo **Profesional, agenda…** → **Para el personal** → indicadores

1. **Vos** abrís el intent desde el atajo o escribiendo en **Asistente**.
2. **El sistema** pide período y filtro opcional por profesional (PES).
3. **El sistema** muestra resumen numérico (no-show, lead time, etc.).

---

## Web — Resolver conflictos de agenda

**Intent:** `profesional-agenda.resolver-conflictos-flow` — *«conflictos agenda»*

1. **Vos** abrís **Asistente** con ese pedido.
2. **El sistema** lista conflictos pendientes del efector.
3. **Vos** elegís reprogramar o cancelar por cada caso y confirmás.
4. **El sistema** aplica los cambios; los pacientes pueden recibir aviso si está activo.

**Cancelación masiva de un día:** operación de coordinación (AdminEfector); probar según pantalla habilitada en el efector.
