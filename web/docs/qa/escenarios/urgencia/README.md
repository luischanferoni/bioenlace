# Urgencia — guardia y derivación desde la app

[← Escenarios](../README.md)

Hay dos pruebas distintas: urgencia grave en la app (sin turno) y atención en guardia del hospital.

## Consulta de ejemplo

**Paciente en la app (no debe quedar turno ambulatorio):**

> «Tengo un dolor fuerte en el pecho y me cuesta respirar.»

**El sistema debería orientar algo como:**

> «Tu situación requiere atención inmediata. Acudí a la guardia más cercana o llamá al 107.»

**Paciente en guardia (relato al ingreso):**

> «Me duele el abdomen desde esta mañana, con vómitos.»

**Médico de guardia al guardar:**

> «Ingreso por dolor abdominal de seis horas con vómitos. Triage nivel 3. Abdomen blando, dolor en epigastrio. Hidratación, analgesia, laboratorio, control en dos horas.»

---

## Paciente — urgencia en la app

1. **Vos** abrís el **Asistente** y describís síntomas graves (pecho, falta de aire, etc.).
2. **El sistema** pregunta por signos de alarma.
3. **El sistema** **no** ofrece elegir médico ni horario; muestra derivación a guardia o emergencia y el aviso del 107.
4. **Vos** **no** debés ver un turno ambulatorio confirmado en la app.

---

## Personal de salud — guardia presencial

### Ver la cola

1. **Vos** entrás con el centro en modo **guardia**.
2. **Vos** abrís el **tablero de guardia**.
3. **El sistema** muestra quién ingresó, quién espera triage, quién espera médico y quién está siendo atendido.

### Ingreso y triage

1. **Vos** registrás el ingreso del paciente de prueba.
2. **Vos** completás el triage: nivel de urgencia, motivo y signos si los cargás.
3. **El sistema** pasa el caso a espera de médico.

### Atender

1. **Vos** te asignás el caso o iniciás la atención.
2. **El sistema** abre la pantalla de atención de guardia.
3. **Vos** escribís o dictás la evolución (guion de arriba) y guardás.
4. **El sistema** actualiza el estado en el tablero.

### Cerrar el caso

1. **Vos** das el **alta**, **derivás** a otro hospital o **pedís internación**, según corresponda.
2. **El sistema** saca el caso de la cola activa.

Si internás al paciente, seguí con [Internación](../internacion/README.md).

---

## Cuándo llegan los avisos

| Cuándo | Quién | Qué debería pasar |
|--------|-------|-------------------|
| Urgencia grave en la app | Paciente | Mensaje en pantalla; **no** confirmación de turno |
| Caso crítico en guardia | Equipo | Aviso al celular del personal, si el centro lo tiene activo |
| Médico toma un caso | Médico | Aviso en la app Personal de Salud, si está configurado |
| Alta de guardia | Paciente | Resumen de la atención o seguimiento, si el programa lo envía |

---

[Internación](../internacion/README.md) · [Medicina general](../ambulatorio/medicina-general.md)
