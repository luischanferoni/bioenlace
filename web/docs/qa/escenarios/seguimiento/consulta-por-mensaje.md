# Seguimiento — consulta clínica por mensaje (sin turno)

[← Seguimiento](./README.md)

Para dudas sobre tratamiento, renovación de recetas o evolución **sin** un malestar nuevo agudo. Si el paciente tiene dolor fuerte o síntomas de alarma, debe usar **Atención** / necesito atención, no este camino.

Nombre de producto: **consulta clínica por mensaje** (técnico: consulta async / `SOLICITUD_ASYNC`). Flujo del asistente: **Consultas y seguimiento** (`atencion.consultas-seguimiento-flow`).

## Consulta de ejemplo

**Paciente:**

> «Quiero consultar si puedo tomar ibuprofeno con la medicación que ya tomo para la presión.»

**Médico respondiendo por mensaje:**

> «Podés tomar ibuprofeno ocasional si no tenés antecedente de úlcera ni problema renal. Tomalo con comida. Si el mareo sigue, coordinamos turno presencial.»

---

## Paciente

1. **Vos** abrís el **Asistente** y elegís **Consultas y seguimiento** (atajo o frase parecida).
2. **Vos** elegís **Consulta general** y escribís tu mensaje (guion de arriba).
3. **El sistema** crea la **consulta clínica por mensaje** (sin turno ni fecha de videollamada).
4. **El sistema** la muestra en **Inicio** con acceso al **chat**.
5. **Vos** esperás la respuesta del equipo.
6. Cuando el médico contesta, **vos** recibís un **aviso** y leés el mensaje en el chat.

**Qué verificar**

- En **Mis turnos** no aparece un horario presencial por este flujo.
- El chat permite seguir escribiendo según las reglas del centro (consulta async abierta).

---

## Personal de salud

1. **Vos** abrís **Pacientes del día** (web o app Personal de Salud).
2. **El sistema** muestra arriba **Consultas clínicas por mensaje** con las solicitudes pendientes.
3. **Vos** elegís la del paciente de prueba y tocás **Tomar y responder**.
4. **El sistema** abre el chat con el mensaje del paciente.
5. **Vos** escribís la respuesta (guion del médico).
6. **El sistema** avisa al paciente en su celular.

**Qué verificar**

- Otro profesional no debería tomar el mismo caso si ya está asignado (según permisos del centro).
- La respuesta queda visible para el paciente en el chat.

---

## Cuándo llegan los avisos

| Cuándo | Quién | Qué debería pasar |
|--------|-------|-------------------|
| Al enviar la consulta | Paciente | Consulta visible en Inicio |
| Si pasa mucho tiempo sin respuesta | Personal | Aviso de caso atrasado, si el centro lo usa |
| Cuando el médico responde | Paciente | Aviso de mensaje nuevo |

---

También: [Seguimiento con plan de tratamiento](./plan-tratamiento.md) · [Medicina general](../ambulatorio/medicina-general.md)
