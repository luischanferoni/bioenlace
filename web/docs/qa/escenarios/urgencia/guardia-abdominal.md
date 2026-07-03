# Urgencia — guardia abdominal y derivación en la app

[← Urgencia](./README.md)

Dos pruebas en un solo escenario: **urgencia grave en la app** (sin turno) y **atención en guardia** del hospital (dolor abdominal).

## Consulta de ejemplo

**Paciente en la app (no debe quedar turno ambulatorio):**

> «Tengo un dolor fuerte en el pecho y me cuesta respirar.»

**El sistema debería orientar algo como:**

> «Tu situación requiere atención inmediata. Acudí a la guardia más cercana o llamá al 107.»

**Paciente en guardia (relato al ingreso):**

> «Me duele el abdomen desde esta mañana, con vómitos. Sin fiebre.»

**Médico de guardia al guardar:**

> «Ingreso por dolor abdominal de seis horas con vómitos. Triage nivel 3. Abdomen blando, dolor en epigastrio. Hidratación, analgesia, laboratorio, control en dos horas.»

---

## Paciente — urgencia grave en la app

1. **Vos** abrís el **Asistente** y describís síntomas graves (dolor de pecho, falta de aire, etc.) — primer guion de arriba.
2. **El sistema** pregunta por **signos de alarma**.
3. **El sistema** **no** ofrece elegir médico ni horario; muestra derivación a guardia o emergencia y el aviso del **107**.
4. **Vos** revisás **Mis turnos**: **no** debe haber un turno ambulatorio nuevo confirmado por este camino.

**Qué verificar**

- Pantalla de derivación clara; sin calendario de turnos.
- Si probás un malestar **leve** (tos sin alarmas), el flujo **sí** puede llevar a turno ambulatorio — usá [medicina general](../ambulatorio/medicina-general.md) para eso.

---

## Personal de salud — preparar guardia

1. **Vos** entrás con el centro en modo **guardia** (sesión operativa con efector).
2. **Vos** abrís el **tablero de guardia**.
3. **El sistema** muestra la cola: ingresos, espera de triage, espera de médico y en atención.

---

## Personal de salud — ingreso y triage

1. **Vos** registrás el ingreso del paciente de prueba (dolor abdominal, guion de guardia).
2. **Vos** completás el **triage**: nivel de urgencia, motivo y signos vitales si los cargás.
3. **El sistema** pasa el caso a **espera de médico**.

**Qué verificar**

- El paciente aparece en la columna correcta del tablero.
- Casos con triage muy grave pueden generar **aviso al celular** del personal, si el centro lo tiene activo.

---

## Personal de salud — atender

1. **Vos** te asignás el caso o iniciás la atención.
2. **El sistema** abre la pantalla de atención de guardia (captura clínica en contexto de guardia).
3. **Vos** escribís o dictás la evolución (guion del médico) y guardás.
4. **El sistema** actualiza el estado en el tablero.

**Qué verificar**

- No se mezcla con flujo ambulatorio (sin «preparar consulta» ni motivos pre-turno).
- La nota queda en la historia vinculada al episodio de guardia.

---

## Personal de salud — cerrar el caso

Elegí **una** salida según lo que quieras probar:

### Alta de guardia

1. **Vos** das el **alta** ambulatoria desde guardia.
2. **El sistema** saca el caso de la cola activa.
3. El paciente puede recibir **resumen de atención** en la app.

### Internación

1. **Vos** pedís **internación** desde la atención.
2. **El sistema** inicia el flujo de cama.
3. Seguí con [internación — neumonía](../internacion/episodio-neumonia.md) (mismo flujo de ingreso, otro diagnóstico).

### Derivación

1. **Vos** registrás **derivación** a otro hospital o servicio.
2. **El sistema** cierra o marca el caso según reglas del efector.

---

## Cuándo llegan los avisos

| Cuándo | Quién | Qué debería pasar |
|--------|-------|-------------------|
| Urgencia grave en la app | Paciente | Mensaje en pantalla; **sin** confirmación de turno |
| Triage crítico en guardia | Equipo | Aviso al personal asignado, si está configurado |
| Médico toma un caso | Médico | Aviso en Personal de Salud, si aplica |
| Alta de guardia | Paciente | Resumen de la atención en la app, si el centro lo envía |

---

[Internación](../internacion/episodio-neumonia.md) · [Medicina general](../ambulatorio/medicina-general.md)
