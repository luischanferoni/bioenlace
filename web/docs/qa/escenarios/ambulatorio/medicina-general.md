# Medicina general — turno presencial

[← Ambulatorio](./README.md)

## Consulta de ejemplo

**Paciente al pedir turno (asistente):**

> «Tengo un poco de tos desde hace tres días, sin fiebre. Quiero un turno con el médico clínico.»

**Paciente al preparar la consulta (chat, unas horas antes del turno):**

> «La tos es seca, más por la noche. No tuve fiebre. Tomé solo miel. No tengo dificultad para respirar.»

**Médico en la consulta (al guardar la atención):**

> «Paciente de 34 años, consulta por tos seca de tres días de evolución, sin fiebre ni dificultad para respirar. Buen estado general, auscultación sin ruidos agregados. Cuadro viral de vías respiratorias altas. Hidratación, analgesia si molesta, control si empeora o aparece fiebre.»

---

## Paciente

### Sacar turno

1. **Vos** abrís el **Asistente** (atajo **Atención**) y contás el malestar leve con tus palabras o el guion de arriba.
2. **El sistema** hace unas preguntas breves: motivo, si hay signos de alarma, zona del cuerpo y cómo evolucionó.
3. **Vos** elegís **Medicina clínica**, el centro, el profesional y un horario libre.
4. **El sistema** confirma el turno y lo muestra en **Inicio** y en **Mis turnos**.

### Preparar la consulta (unas horas antes)

1. Cuando faltan unas **cuatro horas** para el turno, **vos** entrás desde **Inicio** o **Mis turnos** y elegís **Preparar tu consulta**.
2. **El sistema** muestra un solo paso — **contanos tus motivos** — y abre un chat.
3. Al entrar, **ves** una burbuja del sistema con preguntas guía (motivo principal, desde cuándo, evolución, medicación, alarmas). Si al sacar turno elegiste una zona del cuerpo, pueden sumarse preguntas extra (por ejemplo pecho o cabeza).
4. **Vos** respondés en **un mensaje o varios**, en texto libre, por **audio** o con **fotos**.
5. **El sistema** guarda todo; **hasta pocos minutos antes del horario** podés seguir enviando. Después cierra el chat y arma un **resumen** para el médico.

**Qué verificar**

- Antes de las cuatro horas, **Preparar tu consulta** no debería dejarte cargar motivos (o el paso aparece como «próximamente»).
- La burbuja guía se ve al abrir el chat aunque todavía no hayas escrito nada.
- Tras enviar un mensaje, **queda** en el hilo del chat.

### Día del turno

1. **Vos** podés recibir avisos en el celular recordando el turno.
2. **Vos** podés escribir en el asistente *«confirmo que voy»* si el centro pide confirmar asistencia.
3. **Vos** asistís al consultorio a la hora acordada.
4. Después de la consulta, **el sistema** puede ofrecerte seguimiento en la app si el centro lo tiene activo.

---

## Personal de salud

### Antes de la hora

1. **Vos** abrís **Pacientes del día** o tu agenda del día (web o app Personal de Salud).
2. **El sistema** muestra el turno del paciente.

Si abrís la historia demasiado pronto:

1. **El sistema** puede indicar que todavía no está disponible y pedirte esperar hasta unos minutos antes del turno.

### Revisar lo que envió el paciente

1. **Vos** abrís la historia o línea de tiempo del paciente con ese turno en contexto.
2. **El sistema** muestra, si el paciente cargó motivos: el **resumen** del chat (cuando ya cerró la ventana y se procesó) y, si el centro lo usa, cuestionario de pre-consulta por cohorte, y signos vitales.
3. Si el paciente **no** cargó motivos, no hay resumen; la captura sigue disponible igual.

### Atender

1. **Vos** iniciás la atención desde el turno.
2. **Vos** dictás o escribís la evolución (podés usar el guion del médico).
3. **El sistema** propone un borrador ordenado.
4. **Vos** revisás, corregís y tocás **Guardar**.
5. **El sistema** registra la consulta; el paciente la ve en su historial.

---

## Cuándo llegan los avisos

| Cuándo | Quién | Qué debería pasar |
|--------|-------|-------------------|
| Al confirmar el turno | Paciente | Aviso de turno reservado (si las notificaciones están activas) |
| Unas cuatro horas antes del turno | Paciente | Recordatorio para preparar la consulta; se abre el chat de motivos con guía |
| Pocos minutos antes del cierre del chat | Paciente | Último aviso para cargar motivos (si aún no lo hizo) |
| Pocos minutos antes del turno | Médico | Ya puede abrir la historia; si el paciente cargó motivos, ve el resumen |
| Poco antes del horario | Paciente | Recordatorio del turno |
| Después de la consulta | Paciente | Aviso de resumen de atención o encuesta de seguimiento, si el centro lo envía |

En el entorno de prueba los avisos pueden demorar un poco; si no llegan, consultá con quien administra el servidor.

---

También podés probar: [Teleconsulta](./teleconsulta.md) · [Odontología](./odontologia.md) · [Cohorte pre/post consulta](./cohorte-pre-post-consulta.md) (si el centro tiene cohortes activas)
