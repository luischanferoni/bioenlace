# Internación — Ingreso, evolución y alta (IMP)

[← Escenarios](../README.md) · Producto: [internacion.md](../../../producto/internacion.md)

## De qué se trata

Episodio de **internación en piso**: ingreso a cama, evoluciones clínicas durante la estadía y **alta estructurada** con epicrisis. El paciente **no opera** el circuito desde la app en el día a día; el equipo usa **web** o **app Personal de Salud** con sesión `encounter_class` internación (IMP).

**Encounter:** IMP con `parent=INTERNACION`, `parent_id=<id_internacion>`.

**Intents típicos:** `internacion.mapa-camas-flow`, `internacion.alta-estructurada-flow`, `internacion.cambio-cama-flow`.

---

## Prerrequisitos QA

| Requisito | Notas |
|-----------|--------|
| Efector con módulo internación y camas cargadas | Al menos una cama libre |
| Usuario staff con sesión **internación** | [transversal.md](../../staff/transversal.md) |
| Paciente de prueba (persona en MPI) | Alta en guardia o ingreso directo |
| Plantilla de epicrisis activa (opcional) | ABM `/internacion-epicrisis-plantilla` |
| Médico / enfermería con permiso captura IMP | Mismo pipeline que ambulatorio |

---

## Consulta de ejemplo (guion)

### Lo que dice el paciente (guardia / admisión)

> «Me internan por neumonía. Ya me hicieron la placa en guardia.»

*(El paciente no usa la app para el mapa de camas; el relato es para admisión o ingreso desde guardia.)*

### Lo que dice el médico de piso (evolución — captura)

> «Día 2 de internación por neumonía adquirida en comunidad. Afebril 24 h, saturación 97% con O2 discontinua. Auscultación: estertores basales derechos en mejoría. Continúa antibiótico EV. Deambulación asistida. Laboratorio control mañana.»

### Lo que dice el médico (alta — epicrisis)

> «Egreso por mejoría clínica. Diagnóstico principal: neumonía NAC. Tratamiento completado. Alta con amoxicilina oral 3 días más. Control ambulatorio en 7 días. Reposo relativo e hidratación.»

---

## Paciente — paso a paso

El paciente **no** gestiona internación desde la app. Lo que sí puede validarse del lado paciente:

1. **Tras el alta**, si el programa lo prevé, **el sistema** puede enviar **seguimiento post-alta** o resumen de atención (push / Mis atenciones).
2. Si el paciente tenía **turnos ambulatorios** previos, no se mezclan con el episodio IMP en el mismo flujo.

Para QA integral, enfocá la prueba en **staff**; el paciente es dato de prueba en admisión/ingreso.

---

## Personal de salud — paso a paso

### 1. Mapa de camas

**Intent:** `internacion.mapa-camas-flow` o menú Internación

1. **Vos** fijás sesión en modo **internación**.
2. **Vos** abrís el **mapa de camas**.
3. **El sistema** muestra pisos/salas: libre, ocupada, bloqueada, aislamiento.
4. **Vos** seleccionás una cama **libre** para ingreso o una **ocupada** para atender.

### 2. Ingreso

1. **Vos** iniciás **ingreso** (mapa, ficha o desde guardia: `internacion/create?id_guardia=`).
2. **El sistema** pide datos del episodio y confirma cama.
3. **Vos** confirmás.
4. **El sistema** marca la cama **ocupada** y crea el episodio de internación.

Desde guardia: [urgencia](../urgencia/README.md) → internar paciente.

### 3. Atender en piso (evolución)

1. **Vos** elegís **Atender** desde la cama o la ficha.
2. **El sistema** abre **timeline** con contexto `parent=INTERNACION`.
3. **Vos** dictás o escribís la evolución (guion día 2).
4. **El sistema** propone borrador; **vos** guardás.
5. **El sistema** vincula el encounter IMP al episodio; al volver al mapa **se ve** la cama ocupada con el mismo paciente.

Detalle captura: [medico/captura-clinica.md](../../medico/captura-clinica.md) (§ Internación).

### 4. Cambio de cama (opcional en la misma prueba)

**Intent:** `internacion.cambio-cama-flow`

1. **Vos** pedís traslado a otra cama.
2. **El sistema** lista camas libres.
3. **Vos** confirmás.
4. **El sistema** libera la cama anterior, ocupa la nueva **sin** cambiar el id de internación.

### 5. Alta estructurada

**Intent:** `internacion.alta-estructurada-flow`

1. **Vos** iniciás **alta estructurada** desde la ficha o el asistente.
2. **El sistema** guía checklist, tipo de alta y **plantilla de epicrisis** (opcional).
3. **Vos** editás la epicrisis (guion de egreso) y confirmás.
4. **El sistema** ejecuta externación: cama **libre**, episodio **cerrado**.

### 6. Ficha administrativa

1. **Vos** abrís `/internacion/view` (datos administrativos: cama, fechas, obra social).
2. **El sistema** **no** muestra pestañas clínicas MVC legacy; enlace a historia si aplica.

---

## Notificaciones — cuándo esperar qué

| Momento | Quién | Qué esperar |
|---------|-------|-------------|
| Ingreso desde guardia | Staff | Caso sale de cola guardia; cama ocupada en mapa |
| Inicio ingreso con camas libres | Staff | Sugerencia de camas candidatas (si configurado) |
| Alta de internación | Paciente | Seguimiento post-alta / encuesta según programa (AGT-06) |
| Resultado crítico en internado | Staff / paciente | Según reglas laboratorio del efecto |

No aplican pushes **JOURNEY_PRECONSULTA** de turno ambulatorio durante la estadía (salvo turno ambulatorio aparte).

---

## Qué validar (checklist)

| ID | Validación |
|----|------------|
| INT-01 | Mapa refleja estados libre / ocupada / bloqueada / aislamiento |
| INT-02 | Ingreso ocupa cama y crea episodio único |
| INT-03 | Captura IMP guarda evolución; visible al reabrir timeline del internado |
| INT-04 | Cambio de cama: mismo episodio, cama nueva ocupada, anterior libre |
| INT-05 | Alta estructurada cierra episodio y libera cama |
| INT-06 | Plantilla epicrisis rellena placeholders (`{paciente}`, `{dias_internacion}`, …) |
| INT-07 | Ingreso desde guardia enlaza `id_guardia` si aplica |
| INT-08 | Ficha administrativa sin pestañas clínicas legacy rotas |

---

## Referencias

- QA staff: [internacion.md](../../staff/internacion.md)
- QA médico: [captura-clinica.md](../../medico/captura-clinica.md)
- Guardia → internación: [urgencia](../urgencia/README.md)
- Producto: [internacion.md](../../../producto/internacion.md) · [superficies-ui.md](../../../producto/superficies-ui.md)
