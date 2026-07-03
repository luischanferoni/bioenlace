# Cohorte — pre y post consulta (care pack)

[← Ambulatorio](./README.md)

Prueba el **cuestionario pre-consulta** y el **seguimiento post-consulta** por cohorte clínica. Requiere que el centro tenga **cohortes activas** y que en el servidor corra el proceso programado que genera los cuestionarios (cada unos cinco minutos). Sin eso, el paso de cuestionario no aparece o queda vacío.

Complementa [medicina general](./medicina-general.md) (chat de motivos con guía).

## Consulta de ejemplo

**Paciente al pedir turno:**

> «Tengo tos y algo de falta de aire leve desde ayer. Ya sé que tengo presión alta. Quiero ver al médico.»

**Paciente en el cuestionario pre-consulta (unas horas antes):**

> Responde las preguntas del formulario: fiebre no, tos sí, toma medicación para la presión todos los días, molestia moderada.

**Paciente en el chat de motivos (mismo día, misma ventana):**

> «La tos es seca, peor de noche. Presión bien en casa. Sin fiebre.»

**Médico en la consulta:**

> «Consulta por tos de un día con disnea leve. Hipertensión en tratamiento. Auscultación con roncus dispersos. Bronquitis aguda. Broncodilatador y control en cuarenta y ocho horas si empeora.»

**Paciente en seguimiento post-consulta (días después, si el centro lo envía):**

> «La tos mejoró un poco. Sigo con la medicación de la presión.»

---

## Antes de probar

1. Confirmá con quien administra el entorno que **cohortes** están activas para pacientes y para el servidor.
2. Usá un paciente de prueba con turno ambulatorio **dentro de las próximas cuatro horas** (o ajustá la hora del turno en staging).
3. Si es la **primera vez** que esa cohorte se usa, el cuestionario puede tardar unos minutos en estar listo tras confirmar el turno; reintentá **Preparar tu consulta** después de esperar.

---

## Paciente

### Sacar turno

1. **Vos** pedís turno por **Atención** contando tos y malestar respiratorio leve (guion de arriba).
2. **El sistema** hace el triage habitual (sin alarmas graves).
3. **Vos** elegís medicina clínica, centro, profesional y horario.
4. **El sistema** confirma el turno.

### Preparar la consulta — chat de motivos

1. Unas **cuatro horas** antes, **vos** entrás a **Preparar tu consulta**.
2. **Vos** completás el paso **contanos tus motivos** (chat con guía), como en [medicina general](./medicina-general.md).

### Preparar la consulta — cuestionario de cohorte

1. En el mismo hub, **el sistema** muestra un segundo paso: **cuestionario pre-consulta** (solo si cohortes activas y el cuestionario ya está generado).
2. **Vos** abrís el formulario y respondés las preguntas (fiebre, síntomas, medicación, etc.).
3. **Vos** enviás las respuestas.
4. **El sistema** confirma que quedó guardado.

**Qué verificar**

- Si cohortes están off, **no** aparece el segundo paso (solo motivos).
- Si el cuestionario aún no se generó, el paso puede no habilitarse; esperá y volvé a entrar.
- Podés hacer motivos y cuestionario en **cualquier orden** dentro de la ventana.

### Día del turno y después

1. **Vos** asistís a la consulta.
2. Tras la atención, si hay seguimiento por cohorte, **el sistema** puede mostrar **Seguimiento post-consulta** en **Mis turnos** o enviar un aviso.
3. **Vos** completás el formulario corto de evolución (por ejemplo cómo sigue la tos).
4. **El sistema** registra la respuesta.

---

## Personal de salud

### Antes de la consulta

1. **Vos** abrís la historia del paciente con el turno en contexto (unos minutos antes del horario).
2. **El sistema** muestra, en orden: resumen del **chat de motivos**, respuestas del **cuestionario pre-consulta por cohorte** y signos vitales si hay.

### Atender y cerrar

1. **Vos** atendés y guardás la consulta (guion del médico).
2. **El sistema** cierra el encuentro y puede disparar el programa de **seguimiento post-consulta** para esa cohorte.

### Si el paciente reporta empeoramiento en el seguimiento

1. **Vos** recibís un aviso en la app Personal de Salud (si el centro lo tiene activo).
2. **Vos** revisás la respuesta del paciente en el seguimiento y actuás según protocolo del efector.

---

## Cuándo llegan los avisos

| Cuándo | Quién | Qué debería pasar |
|--------|-------|-------------------|
| Al confirmar turno | Sistema (fondo) | Encola generación del cuestionario pre-consulta si hace falta |
| Unas cuatro horas antes | Paciente | Recordatorio de preparar consulta (motivos + cuestionario si aplica) |
| Tras finalizar la consulta | Paciente | Aviso de seguimiento post-consulta o resumen de atención |
| Días después (touchpoint) | Paciente | Recordatorio de completar formulario de evolución |
| Empeoramiento en seguimiento | Personal | Alerta al equipo, si las reglas del centro lo disparan |

---

También podés probar: [Medicina general](./medicina-general.md) (sin cohorte) · [Teleconsulta](./teleconsulta.md)
