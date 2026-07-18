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

---

## App — Preparar la consulta (motivos antes del turno)

Turno ambulatorio con encounter vinculado; ventana desde unas **cuatro horas** antes del horario hasta **pocos minutos** antes.

1. **Vos** abrís **Inicio** o **Mis turnos** y tocás **Preparar tu consulta** (o el aviso push equivalente).
2. **El sistema** muestra un paso: **contanos tus motivos de consulta**.
3. **Vos** entrás al chat; **ves** la burbuja guía con preguntas orientativas.
4. **Vos** enviás texto, audio o fotos (uno o varios mensajes).
5. **El sistema** confirma que el mensaje quedó guardado en el hilo.
6. Poco antes del horario **cierra** la carga y arma un resumen para el médico.

**Antes de la ventana**

1. **Vos** intentás preparar la consulta con más de cuatro horas de anticipación.
2. **El sistema** indica que todavía no está disponible o muestra el paso sin poder abrir el chat.

**Después del cierre**

1. **Vos** abrís el chat cuando ya pasó el plazo de carga.
2. **El sistema** no deja enviar más mensajes; si hubo carga previa, puede mostrarse el resumen ya armado.

Escenario completo: [medicina general](../escenarios/ambulatorio/medicina-general.md).

---

**Sin cupo**

1. **Vos** seguís el flujo cuando no hay horarios.
2. **El sistema** ofrece otras fechas, otro profesional o — si aplica — inscripción en lista de espera.

**Filtro sector / provincia**

1. **Vos** tenés sector y provincia en **Configuración**.
2. **El sistema** solo lista centros y profesionales que correspondan a tu contexto.

---

## App — Adelantamiento tras cancelación

1. **Otro paciente** cancela un turno compatible con el tuyo posterior (≥ 24 h).
2. **El sistema** te envía push/alerta “Se liberó un turno más temprano” (sujeto a disponibilidad).
3. **Vos** en **Alertas** tocás **Adelantar mi turno**.
4. **El sistema** reprograma tu turno al horario liberado si sigue libre; si no, informa que ya no está disponible.
5. El cupo que dejás libre queda abierto para reserva normal (sin segunda campaña).

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
