# Internación — ingreso, evolución y alta

[← Escenarios](../README.md)

El paciente no maneja la internación desde la app; el equipo usa la web o la app Personal de Salud con el centro en modo **internación**.

## Consulta de ejemplo

**Paciente al ingreso (lo cuenta admisión o guardia):**

> «Me internan por neumonía. Ya me hicieron la placa en guardia.»

**Médico de piso — evolución del día 2:**

> «Segundo día de internación por neumonía. Sin fiebre hace veinticuatro horas, buena saturación con oxígeno a demanda. Menos ruidos en el pulmón derecho. Sigue antibiótico endovenoso. Camina con ayuda. Laboratorio de control mañana.»

**Médico — alta:**

> «Egreso por mejoría. Neumonía tratada. Antibiótico oral tres días más. Control ambulatorio en una semana. Reposo relativo e hidratación.»

---

## Personal de salud — mapa de camas

1. **Vos** entrás con el centro en modo **internación**.
2. **Vos** abrís el **mapa de camas**.
3. **El sistema** muestra camas libres, ocupadas, bloqueadas o en aislamiento.
4. **Vos** elegís una cama libre para ingresar o una ocupada para atender.

---

## Personal de salud — ingresar paciente

1. **Vos** iniciás el **ingreso** desde el mapa, desde guardia o desde la ficha del paciente.
2. **El sistema** pide los datos del episodio y la cama.
3. **Vos** confirmás.
4. **El sistema** marca la cama como ocupada y abre la internación.

---

## Personal de salud — evolución en el piso

1. **Vos** tocás **Atender** en la cama o en la ficha del internado.
2. **El sistema** abre la historia clínica del paciente en contexto de internación.
3. **Vos** escribís o dictás la evolución (guion del día 2).
4. **Vos** guardás.
5. **El sistema** registra la nota; al volver al mapa **sigue** el mismo paciente en la misma internación.

---

## Personal de salud — cambio de cama (opcional)

1. **Vos** pedís **cambio de cama** (asistente o pantalla de internación).
2. **El sistema** lista camas libres.
3. **Vos** elegís una y confirmás.
4. **El sistema** libera la cama anterior, ocupa la nueva y **mantiene** la misma internación.

---

## Personal de salud — alta

1. **Vos** iniciás el **alta estructurada** desde la ficha o el asistente.
2. **El sistema** guía los pasos: tipo de alta, checklist y texto de epicrisis (puede ofrecer una plantilla).
3. **Vos** completás la epicrisis (guion de alta) y confirmás.
4. **El sistema** da de alta al paciente, **libera** la cama y cierra el episodio.

---

## Paciente — después del alta

1. Tras el alta, **el sistema** puede enviar al paciente un resumen o una encuesta de seguimiento en la app, si el centro lo tiene activo.

---

## Cuándo llegan los avisos

| Cuándo | Quién | Qué debería pasar |
|--------|-------|-------------------|
| Ingreso desde guardia | Personal | El caso sale de la cola de guardia; la cama figura ocupada |
| Al iniciar ingreso | Personal | Sugerencia de camas libres, si el centro lo muestra |
| Después del alta | Paciente | Seguimiento post-alta o encuesta, si el programa lo envía |
| Resultado de laboratorio grave | Paciente o médico | Aviso según reglas del centro |

---

[Urgencia](../urgencia/README.md) (ingreso desde guardia) · [Medicina general](../ambulatorio/medicina-general.md)
