# Ambulatorio — Teleconsulta (videollamada con turno)

[← Ambulatorio](./README.md) · Producto: [teleconsulta-elegibilidad.md](../../../producto/teleconsulta-elegibilidad.md) · [atencion-remota-async.md](../../../producto/atencion-remota-async.md)

## De qué se trata

Paciente con **turno ambulatorio remoto**: reserva por app eligiendo **videollamada** (no presencial ni consulta async por mensaje). El profesional atiende desde agenda con badge **Teleconsulta**; el paciente puede acceder al chat/vínculo de la consulta desde **Mis turnos** cuando el turno es `tipo_atencion=teleconsulta`.

**Intent reserva:** `atencion.necesito-atencion` (paso **Modalidad** → videollamada)  
**Encounter:** AMB, turno teleconsulta.

**No confundir** con consulta **async** (mensaje sin turno) → [seguimiento](../seguimiento/README.md).

---

## Prerrequisitos QA

| Requisito | Notas |
|-----------|--------|
| Servicio con `teleconsulta_politica` ≠ `ninguna` | AdminEfector: `servicio-teleconsulta.configurar-efector-flow` o BD |
| Triage compatible (banda C/D; no banda B ni A) | Ej.: control crónico, trámite, malestar leve sin alarmas |
| Agenda del PES con **Acepto videollamada** activo | `acepta_consultas_online` en configurar agenda |
| Cupos en grilla del profesional | Misma grilla que presencial hoy |
| Usuario paciente y médico de prueba | Misma fecha/hora reservada |
| Opcional: hub teleconsulta sin elegir profesional | Si el efector ofrece slots hub |

---

## Consulta de ejemplo (guion)

### Lo que dice el paciente (app)

**Al reservar:**

> «Necesito control de mi hipertensión. Prefiero atención por videollamada si se puede.»

**En motivos pre-consulta (chat, si aplica ventana journey):**

> «Controlo presión en casa, últimas lecturas 130/85. Sin cefalea ni mareos. Quiero renovar la receta si está bien.»

### Lo que dice el médico (videollamada / captura)

Durante la llamada (oral):

> «Buenos días. ¿Cómo estuviste con la medicación? ¿Tomás la presión en casa?»

Al cerrar, dictado en captura:

> «Teleconsulta control HTA. Paciente asintomático, PA domiciliaria en rango. Continúa mismo esquema. Control presencial en 3 meses o antes si síntomas. Receta renovada.»

---

## Paciente — paso a paso

### 1. Reservar teleconsulta

**Intent:** `atencion.necesito-atencion`

1. **Vos** describís un motivo **control / crónico** (no urgencia; sin alarmas banda A/B).
2. **El sistema** guía triage hasta servicio **Medicina clínica** (u otro con política remota).
3. **El sistema** muestra paso **Modalidad** con opción **Videollamada** (si `teleconsulta_ofrecible`).
4. **Vos** elegís videollamada, centro/slot y horario.
5. **El sistema** confirma turno con indicador de teleconsulta en **Inicio** / **Mis turnos**.

**Si no aparece modalidad:**

- Verificá política del servicio (`ninguna` → solo presencial).
- Banda B o nodo `presencial_preferido` → no ofrece remoto.
- Sin PES con agenda online → no hay cupos teleconsulta.

### 2. Pre-consulta (journey)

Igual que [medicina-general.md](./medicina-general.md): intake, chat de motivos y cohorte dentro de ventana 72 h / 48 h.

### 3. Día del turno

1. **Vos** recibís recordatorios push (confirmación + journey si aplica).
2. **Vos** abrís el turno en **Mis turnos**.
3. **El sistema** muestra badge **Teleconsulta** y acceso al **chat** de la consulta (si `id_consulta` ya existe).
4. **Vos** entrás al vínculo de videollamada según lo que muestre la app en el horario del turno.

---

## Personal de salud — paso a paso

### 1. Configuración previa (una vez)

1. **Vos** (médico) abrís **Configurar mi agenda** y activás **Acepto videollamada en esta agenda**.
2. **Vos** (AdminEfector, opcional) configurás política de teleconsulta del servicio.

### 2. Día de la teleconsulta

1. **Vos** abrís **Pacientes del día** o agenda en app Personal de Salud.
2. **El sistema** muestra el turno con badge **Teleconsulta** (`tipo_atencion`).
3. **Vos** revisás motivos en timeline (intake → resumen → cohorte) como en presencial.
4. **Vos** iniciás la atención (chat/vídeo según cliente) y documentás en **captura clínica**.
5. **El sistema** guarda encounter AMB; paciente puede ver resumen post-atención.

### 3. Insight en turno presencial (opcional, etapa 0)

Si probás un turno **presencial** con triage `sugerido` para remoto:

1. **El sistema** puede mostrar aviso educativo en listado del día (videollamada / mensaje posible).
2. **No** cambia el turno a remoto automáticamente.

---

## Notificaciones — cuándo esperar qué

| Momento | Quién | Qué esperar |
|---------|-------|-------------|
| Reserva teleconsulta | Paciente | Confirmación de turno (push) |
| Ventana motivos (72 h / 48 h) | Paciente | Push journey `JOURNEY_*` (igual presencial) |
| ~1 min antes del turno | Médico | Historia clínica disponible |
| Horario del turno | Paciente / médico | Recordatorio; acceso chat/vídeo en Mis turnos |
| Tras finalizar | Paciente | Resumen de atención; post-consulta si hay pack followup |
| Sin cupo video en hub | Paciente | Mensaje orientando a presencial o consulta por mensaje |

---

## Qué validar (checklist)

| ID | Validación |
|----|------------|
| TEL-01 | Con política y triage correctos, aparece paso Modalidad con videollamada |
| TEL-02 | Turno persistido con `tipo_atencion=teleconsulta` en API y UI |
| TEL-03 | Sin `acepta_consultas_online`, la reserva teleconsulta falla o no ofrece cupo |
| TEL-04 | Banda B / urgencia: no se ofrece teleconsulta en reserva |
| TEL-05 | Badge Teleconsulta en agenda médico (web y app Personal de Salud) |
| TEL-06 | Motivos pre-turno visibles en timeline antes de atender |
| TEL-07 | Captura guarda encounter; paciente ve atención en historial |
| TEL-08 | Paciente accede al chat desde Mis turnos en turno teleconsulta |

---

## Referencias

- Ambulatorio presencial + journey: [medicina-general.md](./medicina-general.md)
- Async (mensaje): [seguimiento](../seguimiento/README.md)
- Producto: [teleconsulta-elegibilidad.md](../../../producto/teleconsulta-elegibilidad.md)
- QA médico: [captura-clinica.md](../../medico/captura-clinica.md) · [turnos.md](../../medico/turnos.md)
