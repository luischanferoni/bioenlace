# Ambulatorio — Odontología (dolor dental / control)

[← Ambulatorio](./README.md) · Producto: [medicina-clinica-hub-reserva.md](../../../producto/medicina-clinica-hub-reserva.md)

## De qué se trata

Paciente que necesita **odontología**: dolor en pieza dental, control semestral o tratamiento ya indicado. En el hub de reserva, el paciente **no elige odontólogo directamente** salvo que el efector lo permita con **derivación vigente** del clínico; en QA conviene tener ambos caminos claros.

**Encounter:** AMB, workflow de odontología en `EncounterDefinition` (piezas, CPO, prácticas odontológicas).

---

## Prerrequisitos QA

| Requisito | Notas |
|-----------|--------|
| Servicio **Odontología** con agenda y profesional odontólogo | PES odontológico |
| Paciente de prueba | Contexto sector/provincia |
| **Camino A:** derivación del clínico al servicio odontológico | Registrar en captura clínica previa o asistente |
| **Camino B:** turno odontológico directo | Solo si el efector lo permite fuera del hub |
| Médico odontólogo con sesión ambulatoria | Para día de atención |

---

## Consulta de ejemplo (guion)

### Lo que dice el paciente (app / asistente)

**Si entra por clínico primero (hub):**

> «Me duele una muela abajo a la derecha cuando como algo frío.»

*(El clínico deriva a odontología; luego el paciente saca turno con derivación.)*

**Motivos pre-consulta (chat odontológico):**

> «Dolor en pieza inferior derecha, empeora con frío, desde hace una semana. No hinchazón ni fiebre.»

### Lo que dice el odontólogo (captura)

> «Paciente consulta por dolor a estímulos térmicos en pieza 4.6. Examen: caries oclusal profunda, percusión negativa, sin signos de absceso. Plan: obturación en sesión actual o en próximo turno según disponibilidad. Indicaciones postoperatorias si procede.»

*(Completar campos del workflow: pieza, hallazgos, práctica realizada.)*

---

## Paciente — paso a paso

### Camino recomendado — con derivación

1. **Vos** reservás turno de **medicina clínica** con malestar dental leve (`atencion.necesito-atencion`).
2. **El médico clínico** atiende y registra **derivación a odontología**.
3. **Vos** sacás turno odontológico (asistente o pantalla de turnos con derivación vigente).
4. **El sistema** confirma turno en odontología.

### Pre-consulta (si aplica al servicio)

1. Dentro de la ventana de motivos, **vos** completás chat (y intake/cohorte si el efector los tiene para AMB).
2. **El sistema** asocia motivos al encounter del turno odontológico.

### Día del turno

1. **Vos** asistís al consultorio odontológico.
2. Tras la atención, **el sistema** muestra la visita en **Mis atenciones**.

---

## Personal de salud — paso a paso

### Clínico (si aplica derivación)

1. **Vos** atendés en medicina general y registrás derivación a odontología en captura.
2. **El sistema** deja la derivación disponible para reserva odontológica.

### Odontólogo — día de atención

1. **Vos** abrís el turno desde **Pacientes del día**.
2. **Vos** revisás motivos en timeline (mismo orden que medicina general: intake → resumen → cohorte).
3. **Vos** abrís captura con **formulario de odontología** (piezas, prácticas).
4. **Vos** dictás el guion y completás campos estructurados (pieza 4.6, obturación, etc.).
5. **El sistema** guarda el encounter; planillas ministeriales C7 si el efector las usa ([reportes-nomenclador.md](../../staff/reportes-nomenclador.md)).

---

## Notificaciones — cuándo esperar qué

| Momento | Quién | Qué esperar |
|---------|-------|-------------|
| Reserva turno odontológico | Paciente | Confirmación / recordatorio de turno (igual que ambulatorio general) |
| Ventana motivos (si aplica) | Paciente | Push journey `JOURNEY_*` según fases habilitadas |
| Cierre ventana motivos | — | Resumen IA en timeline odontólogo |
| Post atención | Paciente | Resumen de atención; post-consulta cohorte si configurada |

No hay circuito de guardia ni bandeja async en este escenario salvo que el paciente use por error [seguimiento](../seguimiento/README.md).

---

## Qué validar (checklist)

| ID | Validación |
|----|------------|
| ODO-01 | Reserva odontológica exitosa (con derivación si el hub lo exige) |
| ODO-02 | Timeline staff muestra motivos pre-turno |
| ODO-03 | Formulario odontológico carga campos de especialidad (no workflow genérico solo) |
| ODO-04 | Guardado persiste pieza/práctica; visible al reabrir historia |
| ODO-05 | Planilla C7 genera sin error si el efector la usa (opcional) |

---

## Referencias

- QA médico: [captura-clinica.md](../../medico/captura-clinica.md) (§ Odontología)
- QA staff: [reportes-nomenclador.md](../../staff/reportes-nomenclador.md) (§ Planilla odontología)
- Producto hub: [medicina-clinica-hub-reserva.md](../../../producto/medicina-clinica-hub-reserva.md)
