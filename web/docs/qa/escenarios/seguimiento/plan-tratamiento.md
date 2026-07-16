# Seguimiento — plan de tratamiento y turno de control

[← Seguimiento](./README.md)

Paciente con **plan de tratamiento activo** (CarePlan): renovar medicación (varios medicamentos, sin texto libre), solicitar ajuste (medicamentos + motivo), contar evolución o pedir turno de control.

## Consulta de ejemplo

**Paciente — renovar receta:**

> «Necesito renovar la receta de mi tratamiento para la presión. Me quedan dos días.»

**Paciente — evolución:**

> «Vengo tomando la medicación hace dos semanas. Me siento mejor pero a veces me mareo al pararme.»

**Médico en turno de seguimiento (si el paciente pidió turno):**

> «Control de presión en tratamiento. Mejoría con mareos leves al pararse. Ajusto horario del medicamento. Control en quince días.»

---

## Paciente — desde el plan de tratamiento

1. **Vos** entrás al **detalle de tu plan** en la app o desde el asistente (**Consultas y seguimiento**).
2. **Vos** elegís el plan activo (si no venís del detalle) y qué necesitás: renovar medicación, solicitar ajuste, duda, contar evolución o pedir turno.
3. Si elegís **renovar medicación**, marcás uno o más medicamentos del plan y confirmás **sin** escribir texto.
4. Si elegís **solicitar ajuste**, marcás medicamentos y escribís el motivo del cambio.
5. Si elegís **duda** o **evolución**, escribís el mensaje — ver [consulta clínica por mensaje](./consulta-por-mensaje.md).
6. Si elegís **turno**, elegís presencial o videollamada (si aparece) y un horario.
7. **El sistema** confirma el turno o la consulta clínica por mensaje.

**Qué verificar**

- El plan activo/on-hold se lista solo si el médico lo indicó previamente; sin planes, el flujo se corta con mensaje claro.
- En renovación/ajuste solo aparecen medicamentos activos del CarePlan elegido.
- En la bandeja staff, la solicitud muestra operación (renovación/ajuste) y nombres de medicamentos.
- Recordatorios de medicación o controles aparecen si el centro los activó.

---

## Paciente — turno de seguimiento ambulatorio

1. Tras confirmar turno de control, **el sistema** lo muestra en **Mis turnos** como cualquier turno ambulatorio.
2. Unas **cuatro horas** antes, **vos** podés **preparar la consulta** (chat de motivos con guía) — igual que [medicina general](../ambulatorio/medicina-general.md).
3. **Vos** asistís o te conectás según modalidad.

---

## Personal de salud — consulta clínica por mensaje del plan

1. Igual que [consulta clínica por mensaje](./consulta-por-mensaje.md), pero el caso puede mostrar contexto del **plan** en la bandeja.
2. **Vos** respondés o derivás a turno presencial si hace falta.

---

## Personal de salud — turno de seguimiento

1. **Vos** atendés el turno desde la agenda como un turno ambulatorio normal.
2. **Vos** revisás en la historia el contexto del seguimiento y el plan.
3. **Vos** guardás la consulta (guion del médico o el tuyo).

**Qué verificar**

- Motivos pre-turno y resumen del chat se comportan igual que ambulatorio.
- No aparece cuestionario de cohorte salvo que el centro tenga cohortes y el perfil aplique.

---

## Cuándo llegan los avisos

| Cuándo | Quién | Qué debería pasar |
|--------|-------|-------------------|
| Recordatorios del plan | Paciente | Medicación o controles, si están activos |
| Consulta clínica por mensaje enviada | Paciente | Visible en Inicio |
| Respuesta del médico | Paciente | Aviso de mensaje nuevo |
| Turno de seguimiento reservado | Paciente | Confirmación como en ambulatorio |
| Cuatro horas antes del turno | Paciente | Preparar consulta (motivos con guía) |

---

[Consulta clínica por mensaje](./consulta-por-mensaje.md) · [Medicina general](../ambulatorio/medicina-general.md)
