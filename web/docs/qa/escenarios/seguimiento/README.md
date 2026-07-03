# Seguimiento — Consulta general y plan de tratamiento

[← Escenarios](../README.md) · Producto: [consultas-seguimiento.md](../../../producto/consultas-seguimiento.md) · [atencion-remota-async.md](../../../producto/atencion-remota-async.md)

## De qué se trata

Paciente que **no** describe un malestar agudo nuevo para reservar con `atencion.necesito-atencion`, sino que:

- Quiere una **consulta general por mensaje** (async), o
- Tiene un **plan de tratamiento activo** y necesita renovar medicación, hacer una pregunta, contar evolución o pedir turno de seguimiento.

**Intent:** `atencion.consultas-seguimiento-flow`  
**Encounter:** `SOLICITUD_ASYNC` (mensaje) o AMB con turno de seguimiento.

**No confundir** con el journey pre-turno ambulatorio ([recorrido-pre-post-consulta.md](../../../producto/recorrido-pre-post-consulta.md)).

---

## Prerrequisitos QA

| Requisito | Notas |
|-----------|--------|
| Usuario paciente con app | |
| **Camino async:** bandeja «Consultas por mensaje» habilitada en el efector | Etapa 3+ [atencion-remota-async.md](../../../producto/atencion-remota-async.md) |
| **Camino plan:** paciente con **care plan activo** | [planes-de-tratamiento.md](../../../producto/planes-de-tratamiento.md) |
| Médico con sesión en el servicio del async o del plan | PES correcto |
| Opcional: teleconsulta habilitada | Si probás rama «pedir turno» con video |

---

## Consulta de ejemplo (guion)

### Lo que dice el paciente (app)

**Consulta general (async):**

> «Quiero consultar si puedo tomar ibuprofeno con la medicación que ya tomo.»

**Seguimiento de plan — renovar:**

> «Necesito renovar la receta de mi tratamiento para la presión. Me quedan dos días.»

**Seguimiento — evolución:**

> «Vengo tomando la medicación hace dos semanas. Me siento mejor pero a veces me mareo al pararme.»

### Lo que dice el médico (bandeja async o captura)

**Respuesta async (chat):**

> «Podés tomar ibuprofeno ocasional si no tenés antecedente de úlcera ni insuficiencia renal. Tomalo con comida. Si el mareo persiste, coordinamos turno presencial.»

**En turno de seguimiento (captura):**

> «Control de HTA en tratamiento. Paciente refiere mejoría con episodios de ortostatismo leve. Ajusto horario de antihipertensivo y solicito control en 15 días.»

---

## Paciente — paso a paso

### A. Consulta general por mensaje

**Intent:** `atencion.consultas-seguimiento-flow` → **Consulta general**

1. **Vos** abrís **Asistente** → atajo **Consultas y seguimiento** (o frase equivalente).
2. **Vos** elegís consulta general y escribís el mensaje (guion ibuprofeno).
3. **El sistema** crea solicitud async (`SOLICITUD_ASYNC`) sin turno.
4. **El sistema** muestra la consulta activa en **Inicio** con acceso al chat.

### B. Seguimiento desde plan de tratamiento

**Intent:** `atencion.consultas-seguimiento-flow` → **Seguimiento**

1. **Vos** entrás desde **detalle del plan** o el asistente.
2. **Vos** elegís el plan activo y la necesidad (renovar / duda / evolución / turno).
3. **Rama mensaje:** igual que async con contexto del plan.
4. **Rama turno:** elegís modalidad (presencial / teleconsulta si aplica) y horario.

### C. Esperar respuesta

1. **Vos** esperás notificación de respuesta del equipo (push cuando el médico contesta, según configuración).
2. **Vos** abrís el chat y leés la respuesta.

---

## Personal de salud — paso a paso

### Bandeja «Consultas por mensaje»

1. **Vos** abrís **Pacientes del día**; arriba aparece **Consultas por mensaje**.
2. **El sistema** lista solicitudes con SLA, prioridad y mensaje del paciente.
3. **Vos** tomás el caso (**Tomar y responder**).
4. **El sistema** abre chat operativo (`consulta-chat`).
5. **Vos** escribís la respuesta (guion médico).
6. **El sistema** notifica al paciente; el caso puede cerrarse o quedar en seguimiento según flujo.

### Turno de seguimiento (si el paciente eligió turno)

1. **Vos** atendés desde agenda como ambulatorio, con motivo de seguimiento ya contextualizado.
2. **Vos** documentás en captura (guion control HTA).
3. **El sistema** guarda encounter AMB vinculado al turno.

---

## Notificaciones — cuándo esperar qué

| Momento | Quién | Qué esperar |
|---------|-------|-------------|
| Al crear solicitud async | Paciente | Confirmación en app (consulta activa en inicio) |
| SLA por vencer (bandas A/B) | Staff | Push de escalamiento (una vez por solicitud) | Ver `consulta_async_bandeja.yaml` |
| Médico responde en chat | Paciente | Push de nueva respuesta / mensaje |
| Recordatorios de care plan | Paciente | Push de adherencia o touchpoints del plan | [planes-de-tratamiento.md](../../../producto/planes-de-tratamiento.md) |
| Turno de seguimiento reservado | Paciente | Confirmación de turno (igual ambulatorio) |

No aplican pushes **JOURNEY_PRECONSULTA** del encounter journey de turno salvo que el paciente **también** tenga un turno AMB aparte.

---

## Qué validar (checklist)

| ID | Validación |
|----|------------|
| SEG-01 | `consultas-seguimiento-flow` no ofrece triage de malestar nuevo / urgencia |
| SEG-02 | Async: solicitud en bandeja staff con mensaje del paciente |
| SEG-03 | Staff responde; paciente ve mensaje en chat |
| SEG-04 | Seguimiento desde plan: plan y necesidad prefijados si entró desde detalle |
| SEG-05 | Rama turno: reserva exitosa y atención documentada |
| SEG-06 | Frase de malestar agudo en chat clasifica a `necesito-atencion`, no a este flujo |

---

## Referencias

- QA paciente: [asistente.md](../../paciente/asistente.md) · [laboratorio-receta-planes.md](../../paciente/laboratorio-receta-planes.md)
- QA médico: [laboratorio-receta-planes.md](../../medico/laboratorio-receta-planes.md)
- Producto: [consultas-seguimiento.md](../../../producto/consultas-seguimiento.md) · [planes-de-tratamiento.md](../../../producto/planes-de-tratamiento.md)
- Notificaciones: [notificaciones-automaticas.md](../../staff/notificaciones-automaticas.md)
