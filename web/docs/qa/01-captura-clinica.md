# Captura clínica — historia y consulta

[← Índice](./README.md) · Más detalle: [captura-clinica.md](../producto/captura-clinica.md)

---

## Ver la historia de un paciente

1. **Vos** (personal) abrís la historia / línea de tiempo del paciente.
2. **El sistema** lista consultas y episodios anteriores con fecha y tipo.
3. **Vos** abrís uno cerrado o uno en curso.
4. **El sistema** muestra el detalle sin romperse (todo sale del registro clínico nuevo, no de tablas viejas de “consultas”).

---

## Escribir la atención y que la IA te ayude a ordenar

1. **Vos** entrás a la captura (desde un turno, guardia o internación) y escribís en texto libre: motivo, diagnóstico, medicación, estudios, etc.
2. **El sistema** (con IA) te propone un borrador ordenado: qué va en diagnóstico, qué en recetas, qué en pedidos.
3. **Vos** revisás, corregís si hace falta y confirmás.
4. **El sistema** todavía **no** guarda definitivo hasta que vos digas guardar.

---

## Guardar la consulta

1. **Vos** confirmás el guardado.
2. **El sistema** persiste la atención y te muestra que quedó registrada.
3. En la historia del paciente **aparece** el encuentro nuevo con diagnósticos, medicación y prácticas que cargaste.

---

## Dictar o grabar audio en lugar de tipear

1. **Vos** grabás o subís audio en la captura.
2. **El sistema** lo transcribe a texto y sigue el mismo flujo de “analizar → revisar → guardar”.
3. Si el audio no se entiende, **el sistema** te pide que repitas o escribas.

---

## Atender un turno del día

1. **Vos** abrís el turno desde agenda o lista de espera.
2. **El sistema** verifica que seas el profesional (o tengas permiso) para ese turno.
3. Si no corresponde, **el sistema** no te deja entrar a la captura.
4. Si sí, **te lleva** a la pantalla de atención.

---

## Atender en internación (piso)

1. **Vos** entrás a la captura desde el mapa de camas o la ficha del internado.
2. **El sistema** sabe que es un episodio de **internación** y muestra lo que corresponde (incluido balance hídrico, régimen, medicación de piso si está habilitado).
3. **Vos** guardás igual que en ambulatorio.
4. **El sistema** actualiza el episodio de internación, no pantallas sueltas viejas.

---

## Atender en guardia

1. **Vos** iniciás la atención desde el tablero de guardia (ver [03-urgencias-guardia.md](./03-urgencias-guardia.md)).
2. **El sistema** abre la captura ligada a ese ingreso de guardia.
3. Al guardar, **queda** vinculado al circuito de urgencias.

---

## Odontología u oftalmología (si el servicio lo tiene)

1. **Vos** cargás prácticas de odontología (piezas, CPO) u oftalmología (estudios, lentes) según el formulario del servicio.
2. **El sistema** guarda en el modelo clínico de esa especialidad.
3. Las planillas ministeriales de odontología **pueden** tomar esos datos si el efector las usa.

---

## Derivar a otro servicio o efector

1. **Vos** en la captura indicás derivación (a qué servicio/efector va el paciente).
2. **El sistema** registra la derivación como pedido de referencia.
3. En **referencias** o en agenda, el personal ve la derivación pendiente y puede programar turno.

---

## Balance hídrico, régimen y medicación en piso

1. **Vos** cargás balance, régimen alimentario o administración de medicación en la captura de internación.
2. **El sistema** lo guarda como parte del episodio (no en tablas viejas de “consulta internación”).
3. Al volver a abrir el internado, **ves** lo cargado en el resumen clínico.

---

## Sin permiso o sesión vencida

1. **Vos** intentás guardar o ver una historia sin estar logueado o sin permiso.
2. **El sistema** no muestra datos ajenos y te pide iniciar sesión o te dice que no tenés acceso.
