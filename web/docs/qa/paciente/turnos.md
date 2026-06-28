# Turnos (app paciente)

[← Paciente](./README.md) · Más detalle: [turnos.md](../../producto/turnos.md) · Frases: [asistente.md](./asistente.md)

Requisito de contexto: [contexto-registro.md](./contexto-registro.md).

---

## App — Sacar turno

**Intent:** `atencion.necesito-atencion` (Atajo **Atención**) o `turnos.crear-como-paciente` (*«quiero un turno»*, *«sacar turno»*)

1. **Vos** abrís **Asistente** → Atajo **Atención**, o escribís *«quiero un turno»* / *«sacar turno»*.
2. **El sistema** guía servicio, centro, profesional y horario (o triage previo si entraste por **Atención**).
3. **Vos** confirmás el horario.
4. **El sistema** muestra el turno en **Inicio** → próximos turnos.

**Sin cupo**

1. **Vos** seguís el flujo cuando no hay horarios.
2. **El sistema** ofrece otras fechas, otro profesional o — si aplica — inscripción en lista de espera.

**Filtro sector / provincia**

1. **Vos** tenés sector y provincia en **Configuración**.
2. **El sistema** solo lista centros y profesionales que correspondan a tu contexto.

---

## App — Lista de espera (sin cupo)

**Intent:** `turnos.lista-espera-flow` (desde el flujo de reserva)

1. **Vos** llegás al paso sin cupos disponibles.
2. **El sistema** ofrece inscribirte en lista de espera del servicio.
3. **Vos** confirmás.
4. **El sistema** confirma la inscripción; si se libera un turno, podés recibir aviso (push).

---

## App — Cancelar turno

**Intent:** `turnos.cancelar-como-paciente-flow` — Atajo **Turnos** o *«cancelar turno»*

1. **Vos** abrís **Asistente** → Atajo **Turnos** (cancelar), o escribís *«cancelar turno»*.
2. **El sistema** lista turnos pendientes y pide motivo si corresponde.
3. **Vos** confirmás con anticipación suficiente (regla del efector).
4. **El sistema** cancela el turno y libera el cupo.

**Error — cancelación muy tarde**

1. **Vos** intentás cancelar fuera del plazo del efector.
2. **El sistema** rechaza con el motivo.

---

## App — Cambiar fecha u hora

**Intent:** `turnos.modificar-como-paciente-flow` — *«reprogramar»*, *«cambiar mi turno»*

1. **Vos** abrís **Asistente** → Atajo **Turnos** (modificar), o escribís *«reprogramar»* / *«cambiar mi turno»*.
2. **El sistema** lista turnos pendientes y muestra nuevos cupos.
3. **Vos** elegís horario y confirmás.
4. **El sistema** actualiza el turno; el cupo anterior queda libre.

---

## App — Reprogramar turno en resolución

**Intent:** `turnos.reubicar-como-paciente-flow` — *«reubicar turno»*

1. **Vos** abrís el flujo cuando el consultorio cambió la agenda y tu turno quedó **en resolución**.
2. **El sistema** muestra cupos alternativos.
3. **Vos** elegís y confirmás (o cancelás si preferís).
4. **El sistema** cierra la resolución.

---

## App — Confirmar asistencia

**Intent:** `turnos.confirmar-asistencia-flow` — *«confirmo que voy»*, *«voy a ir»*

1. **Vos** escribís en **Asistente** que confirmás asistencia.
2. **El sistema** lista turnos pendientes elegibles.
3. **Vos** confirmás el turno.
4. **El sistema** registra la confirmación para el personal.

---

## App — Aviso cuando cambia un turno

1. **Vos** tenés la app con notificaciones permitidas.
2. **El sistema** envía push al reprogramar o cancelar un turno que te afecta.

---

## App — Política de cancelación / reprogramación

**Intent:** `turnos.consultar-politica-autogestion-flow` — *«cuánto antes puedo cancelar»*

1. **Vos** preguntás en **Asistente** por la política del efector.
2. **El sistema** responde con plazos y reglas de autogestión.
