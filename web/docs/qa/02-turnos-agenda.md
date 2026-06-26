# Turnos y agenda

[← Índice](./README.md) · Más detalle: [turnos.md](../producto/turnos.md) · Frases del asistente: [07-asistente.md](./07-asistente.md)

Antes conviene tener [sesión operativa](./00-transversal.md#elegir-efector-servicio-y-tipo-de-atención) en **ambulatorio** (personal web).

En la **web staff** el acceso principal es el **panel Pacientes** y el **Asistente** (barra lateral). No hay menú legacy de Turnos.

**Convención:** cada flujo indica **Web** o **App**, el **intent** cuando pasa por el asistente, y pasos numerados con resultado esperado.

---

## Web — Lista de espera del día

Equivalente operativo al panel **Pacientes del día** (recepción / consultorio). Sustituye la pantalla legacy `turnos/espera`.

1. **Vos** (personal) entrás con sesión en **ambulatorio** y abrís **Pacientes** en la barra lateral.
2. **El sistema** muestra el panel **Pacientes del día** (turnos de la fecha con datos del paciente; KPIs de agenda si aplican).
3. **Vos** cambiás la fecha si hace falta (ayer / mañana / otra).
4. **El sistema** actualiza la lista de turnos de ese día.

---

## Web — Agenda del día (asistente)

Alternativa al panel para ver citas de un profesional.

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

## App — Sacar turno (paciente)

**Intent:** `atencion.necesito-atencion` (Atajo **Atención**) o `turnos.crear-como-paciente` (*«quiero un turno»*, *«sacar turno»*)

1. **Vos** abrís **Asistente** → Atajo **Atención**, o escribís *«quiero un turno»* / *«sacar turno»*.
2. **El sistema** guía servicio, centro, profesional y horario (o triage previo si entraste por **Atención**).
3. **Vos** confirmás el horario.
4. **El sistema** muestra el turno en **Inicio** → próximos turnos.

**Sin cupo**

1. **Vos** seguís el flujo cuando no hay horarios.
2. **El sistema** ofrece otras fechas, otro profesional o — si aplica — inscripción en lista de espera (ver flujo siguiente).

**Filtro sector / provincia**

1. **Vos** tenés sector y provincia en **Configuración** ([08-registro-contexto-paciente.md](./08-registro-contexto-paciente.md)).
2. **El sistema** solo lista centros y profesionales que correspondan a tu contexto.

---

## App — Lista de espera (sin cupo, paciente)

Cuando no hay horarios, el sistema puede ofrecer anotarse para un hueco liberado.

**Intent:** `turnos.lista-espera-flow` (desde el flujo de reserva, p. ej. tras `atencion.necesito-atencion`)

1. **Vos** llegás al paso sin cupos disponibles.
2. **El sistema** ofrece inscribirte en lista de espera del servicio.
3. **Vos** confirmás.
4. **El sistema** confirma la inscripción; si se libera un turno, podés recibir aviso (push).

---

## App — Cancelar turno (paciente)

**Intent:** `turnos.cancelar-como-paciente-flow` — Atajo **Turnos** (cancelar) o *«cancelar turno»*

1. **Vos** abrís **Asistente** → Atajo **Turnos** (cancelar), o escribís *«cancelar turno»*.
2. **El sistema** lista turnos pendientes y pide motivo si corresponde.
3. **Vos** confirmás con anticipación suficiente (regla del efector).
4. **El sistema** cancela el turno y libera el cupo.

**Error — cancelación muy tarde**

1. **Vos** intentás cancelar fuera del plazo del efector.
2. **El sistema** rechaza con el motivo.

---

## App — Cambiar fecha u hora (paciente)

**Intent:** `turnos.modificar-como-paciente-flow` — Atajo **Turnos** (modificar) o *«reprogramar»*, *«cambiar mi turno»*

1. **Vos** abrís **Asistente** → Atajo **Turnos** (modificar), o escribís *«reprogramar»* / *«cambiar mi turno»*.
2. **El sistema** lista turnos pendientes y muestra nuevos cupos.
3. **Vos** elegís horario y confirmás.
4. **El sistema** actualiza el turno; el cupo anterior queda libre.

---

## Web — Cancelar turno de un paciente (staff)

**Intent:** `turnos.cancelar-para-paciente-flow` — *«cancelar turno del paciente»*, *«cancelar turno paciente»*

1. **Vos** abrís **Asistente** y pedís cancelar el turno de un paciente.
2. **El sistema** pide el turno y motivo opcional.
3. **Vos** confirmás.
4. **El sistema** cancela con permiso staff; el cupo queda libre.

---

## Web — Reprogramar turno en resolución (paciente vía staff)

Cuando el consultorio cambió la agenda y el turno quedó **en resolución**.

**Intent (paciente en app):** `turnos.reubicar-como-paciente-flow` — *«reubicar turno»*

**Intent (conflicto por cambio de agenda):** `turnos.conflicto-agenda-flow` — *«conflicto agenda»*, *«reprogramar por agenda»*

1. **Vos** (paciente o staff según el caso) abrís el flujo correspondiente en **Asistente**.
2. **El sistema** muestra el turno afectado y cupos alternativos.
3. **Vos** elegís y confirmás (o cancelás si preferís).
4. **El sistema** cierra la resolución; el cupo anterior queda libre si reprogramaste.

---

## Web — Programar turno desde una derivación

Tras registrar una derivación en [captura clínica](./01-captura-clinica.md).

**Intent:** `turnos.crear-para-paciente-flow`

1. **Vos** (personal) tenés al paciente en contexto y abrís **Asistente** para crear turno.
2. **El sistema** muestra el flujo de alta; si hay derivación pendiente para ese servicio, la tenés en cuenta al elegir servicio y profesional.
3. **Vos** confirmás el turno.
4. **El sistema** marca la derivación como con turno asignado.

---

## Web — Sobreturno (personal)

**Intent:** `turnos.crear-sobreturno-flow` — *«sobreturno»*

1. **Vos** abrís **Asistente** y escribís *«sobreturno»* (o equivalente).
2. **El sistema** pide paciente, fecha, hora y servicio.
3. **Vos** confirmás.
4. **El sistema** guarda el turno como sobreturno en la agenda del día.

---

## Web — Marcar «no se presentó»

**Intent:** `turnos.no-se-presento-flow` — *«no vino»*, *«ausente»*, *«no se presentó»*

1. **Vos** abrís **Asistente** o marcás desde el turno en el panel, según la pantalla disponible.
2. **El sistema** pide identificar el turno.
3. **Vos** confirmás la inasistencia.
4. **El sistema** actualiza el estado del turno (ausencia).

---

## Web — Calendario y horarios del profesional

Ver o editar la grilla de atención (día/semana, bloqueos, intervalo).

**Intent (ver citas del día):** `turnos.ver-agenda-dia-profesional-flow`

**Intent (ocupación / slots del día):** `turnos.consultar-ocupacion-dia-flow`

**Intent (configurar mi agenda):** `profesional-agenda.configurar-propio` — Atajo **Profesional, agenda y condición laboral** → **Para mí** → configurar agenda

**Intent (configurar agenda de otro profesional):** `profesional-agenda.configurar-staff` — Atajo **Profesional, agenda…** → **Para el personal**

1. **Vos** elegís ver el día, consultar ocupación o abrir la configuración de horarios (propio o de staff).
2. **El sistema** muestra la grilla, los turnos del día o el formulario de horarios según el intent.
3. **Vos** revisás o guardás cambios si estás configurando.
4. **El sistema** refleja turnos, bloqueos y feriados en la agenda.

---

## Web — Indicadores de agenda (ocupación, no-show, etc.)

**Intent:** `turnos.indicadores-agenda-flow` — Atajo **Profesional, agenda y condición laboral** → **Para el personal** → indicadores, o *«cómo está la agenda»*, *«indicadores agenda»*

1. **Vos** abrís el intent desde el atajo o escribiendo en **Asistente**.
2. **El sistema** pide período y filtro opcional por profesional (PES).
3. **El sistema** muestra resumen numérico (no-show, lead time, etc.).

---

## Web — Resolver conflictos de agenda (coordinación staff)

Cuando cambios de horarios dejaron turnos de pacientes en conflicto.

**Intent:** `profesional-agenda.resolver-conflictos-flow` — *«conflictos agenda»*, *«resolver conflicto paciente»*

1. **Vos** abrís **Asistente** con ese pedido.
2. **El sistema** lista conflictos pendientes del efector.
3. **Vos** elegís reprogramar o cancelar por cada caso y confirmás.
4. **El sistema** aplica los cambios; los pacientes pueden recibir aviso si está activo.

**Cancelación masiva de un día**

Operación de coordinación (AdminEfector); **sin intent en catálogo del asistente** a la fecha de este documento. Probar según pantalla operativa habilitada en el efector o pedir al responsable del entorno.

---

## App — Confirmar asistencia (paciente)

**Intent:** `turnos.confirmar-asistencia-flow` — *«confirmo que voy»*, *«voy a ir»*

1. **Vos** escribís en **Asistente** que confirmás asistencia.
2. **El sistema** lista turnos pendientes elegibles.
3. **Vos** confirmás el turno.
4. **El sistema** registra la confirmación para el personal.

---

## App — Aviso cuando cambia un turno

1. **Vos** tenés la app con notificaciones permitidas y el efector con envío activo.
2. **El sistema** envía push al reprogramar o cancelar un turno que te afecta.

---

## App — Política de cancelación / reprogramación

**Intent:** `turnos.consultar-politica-autogestion-flow` — *«cuánto antes puedo cancelar»*, *«puedo cancelar por app»*

1. **Vos** preguntás en **Asistente** por la política del efector.
2. **El sistema** responde con plazos y reglas de autogestión.
