# Captura clínica — historia y consulta

[← Médico](./README.md)

---

## Ver la historia de un paciente

1. **Vos** abrís la historia / línea de tiempo del paciente.
2. **El sistema** lista atenciones anteriores con fecha y tipo.
3. **Vos** abrís una cerrada o una en curso.
4. **El sistema** muestra el detalle completo: fecha, tipo, motivo, diagnósticos, medicación y estudios — sin pantallas vacías ni errores.

---

## Motivos pre-turno (chat guiado y cohorte)

Con turno ambulatorio y dentro de la ventana de historia clínica:

1. **Vos** abrís el timeline del paciente (web o app Personal de Salud) con el turno en contexto.
2. **El sistema** muestra, si el paciente cargó motivos antes del turno, el **resumen** del chat (tras cerrar la ventana de carga y procesar el hilo).
3. Si el centro tiene **cohortes** activas, puede aparecer además el cuestionario de pre-consulta completado.
4. Si el paciente **no** cargó motivos, no hay resumen; **no** bloquea la captura.
5. Si es demasiado pronto antes del turno, **el sistema** puede responder `HC_ANTES_DE_VENTANA` hasta abrir la ventana médico.

Detalle: [recorrido-pre-post-consulta.md](../../producto/recorrido-pre-post-consulta.md).

---

## Escribir la atención con ayuda de IA

1. **Vos** entrás a la captura (desde turno, guardia o internación) y escribís en texto libre: motivo, diagnóstico, medicación, estudios, etc.
2. **El sistema** te propone un borrador ordenado por secciones.
3. **Vos** revisás, corregís y confirmás el análisis.
4. **El sistema** **no** guarda definitivo hasta que vos confirmés **Guardar**.

---

## Guardar la consulta

1. **Vos** confirmás el guardado.
2. **El sistema** confirma que quedó registrada.
3. En la historia del paciente **aparece** la atención nueva con lo que cargaste.

---

## Dictar o grabar audio

1. **Vos** grabás o subís audio en la captura.
2. **El sistema** lo transcribe y sigue el mismo flujo: analizar → revisar → guardar.
3. Si no se entiende el audio, **te pide** repetir o escribir.

---

## Atender un turno del día

1. **Vos** abrís el turno desde el panel **Pacientes del día** o desde **Asistente** (`turnos.ver-agenda-dia-profesional-flow`).
2. **El sistema** verifica que seas el profesional (o tengas permiso) para ese turno.
3. Si no corresponde, **no** te deja entrar a la captura.
4. Si sí, **te lleva** a la pantalla de atención.

Detalle turnos: [turnos.md](./turnos.md).

---

## Atender en internación (piso)

1. **Vos** entrás a la captura desde el mapa de camas o la ficha del internado.
2. **El sistema** muestra las secciones de internación (evolución, balance hídrico, régimen, medicación de piso si aplica).
3. **Vos** guardás igual que en ambulatorio.
4. Al volver al mapa de camas, **ves** lo que guardaste en la ficha del internado.

Mapa de camas: [staff/internacion.md](../staff/internacion.md).

---

## Atender en guardia

1. **Vos** iniciás la atención desde el tablero de guardia.
2. **El sistema** abre la captura de ese ingreso.
3. Al guardar, **queda** vinculada a ese episodio de guardia.

Detalle: [staff/urgencias-guardia.md](../staff/urgencias-guardia.md).

---

## Odontología u oftalmología (si el servicio lo tiene)

1. **Vos** completás el formulario de la especialidad (piezas, CPO, estudios, lentes…).
2. **El sistema** guarda los datos.
3. Si el efector usa planillas ministeriales de odontología, **pueden** incluir esos datos al generar reportes.

---

## Derivar a otro servicio o efector

1. **Vos** indicás la derivación en la captura.
2. **El sistema** la registra.
3. El personal programa turno con **Asistente** (`turnos.crear-para-paciente-flow`); ver [staff/turnos-agenda.md](../staff/turnos-agenda.md).

---

## Balance hídrico, régimen y medicación en piso

1. **Vos** cargás balance, régimen o medicación en la captura del internado.
2. **El sistema** guarda y muestra esos datos en el resumen del internado.
3. Al reabrir la captura, **ves** lo cargado.

---

## Sin permiso o sesión vencida

1. **Vos** intentás guardar o ver una historia sin estar logueado o sin permiso.
2. **El sistema** no muestra datos ajenos y te pide iniciar sesión o te dice que no tenés acceso.
