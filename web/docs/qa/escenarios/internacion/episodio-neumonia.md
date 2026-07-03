# Internación — neumonía (ingreso, piso y alta)

[← Internación](./README.md)

El paciente **no** gestiona la internación desde la app paciente; el equipo usa **web** o **app Personal de Salud** con el centro en modo **internación**. Este escenario parte de un ingreso desde guardia; también podés probar ingreso directo desde admisión.

## Consulta de ejemplo

**Paciente al ingreso (admisión o guardia):**

> «Me internan por neumonía. Ya me hicieron la placa en guardia.»

**Enfermería / médico de piso — evolución día 2:**

> «Segundo día de internación por neumonía. Sin fiebre hace veinticuatro horas, buena saturación con oxígeno a demanda. Menos ruidos en el pulmón derecho. Sigue antibiótico endovenoso. Camina con ayuda. Laboratorio de control mañana.»

**Médico — alta:**

> «Egreso por mejoría. Neumonía tratada. Antibiótico oral tres días más. Control ambulatorio en una semana. Reposo relativo e hidratación.»

---

## Personal de salud — ingreso desde guardia

1. **Vos** atendiste al paciente en **guardia** (ver [urgencia](../urgencia/guardia-abdominal.md) si necesitás el paso previo).
2. **Vos** indicás **internación** desde la atención de guardia.
3. **El sistema** sugiere camas libres o te lleva al flujo de ingreso.
4. **Vos** elegís cama, completás datos del episodio y confirmás.
5. **El sistema** marca la cama como **ocupada** y abre la internación vinculada al ingreso de guardia.

**Qué verificar**

- El caso **sale** de la cola activa de guardia.
- En el **mapa de camas**, la cama figura ocupada con el nombre del paciente.

---

## Personal de salud — mapa de camas

1. **Vos** entrás con el centro en modo **internación** (web o app Personal de Salud).
2. **Vos** abrís el **mapa de camas**.
3. **El sistema** muestra camas libres, ocupadas, bloqueadas o en aislamiento.
4. **Vos** tocás una cama ocupada para ver al internado o una libre para un ingreso nuevo.

---

## Personal de salud — evolución en el piso

1. **Vos** tocás **Atender** en la cama o abrís la ficha del internado.
2. **El sistema** abre la **historia clínica** en contexto de internación (no es un turno ambulatorio).
3. **Vos** escribís o dictás la evolución del día 2 (guion de arriba).
4. **Vos** revisás el borrador y guardás.
5. **El sistema** registra la nota; al volver al mapa el paciente **sigue** en la misma internación y cama.

**Qué verificar**

- No aparece el flujo de **motivos pre-turno** ni **preparar consulta** (eso es solo ambulatorio con turno).
- Las evoluciones previas del episodio se ven en el timeline.

---

## Personal de salud — cambio de cama (opcional)

1. **Vos** pedís **cambio de cama** desde la ficha o el asistente.
2. **El sistema** lista camas libres.
3. **Vos** elegís una y confirmás.
4. **El sistema** libera la cama anterior, ocupa la nueva y **mantiene** el mismo episodio de internación.

---

## Personal de salud — alta estructurada

1. **Vos** iniciás el **alta** desde la ficha del internado o el flujo guiado de alta.
2. **El sistema** guía tipo de alta, checklist y **epicrisis** (puede ofrecer una plantilla del centro).
3. **Vos** completás la epicrisis (guion de alta) y confirmás.
4. **El sistema** da de alta al paciente, **libera** la cama y cierra el episodio.

**Qué verificar**

- La cama vuelve a **libre** en el mapa.
- La epicrisis queda en la historia del paciente.

---

## Paciente — después del alta

1. Tras el alta, **el sistema** puede enviar al paciente en la app un **resumen de atención** o una encuesta de seguimiento, si el centro lo tiene activo.
2. **Vos** (como paciente de prueba) revisás **Mis atenciones** en la app; la internación figura como episodio cerrado.

---

## Cuándo llegan los avisos

| Cuándo | Quién | Qué debería pasar |
|--------|-------|-------------------|
| Ingreso desde guardia | Personal | Cama ocupada; caso fuera de cola de guardia |
| Al iniciar ingreso | Personal | Sugerencia de camas libres, si el centro lo muestra |
| Resultado de laboratorio grave | Paciente o médico | Aviso según reglas del centro |
| Después del alta | Paciente | Resumen o encuesta post-alta, si el programa lo envía |

---

[Urgencia — guardia](../urgencia/guardia-abdominal.md) · [Medicina general](../ambulatorio/medicina-general.md)
