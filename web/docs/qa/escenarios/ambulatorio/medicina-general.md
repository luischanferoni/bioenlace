# Ambulatorio — Medicina general (control / malestar leve)

[← Ambulatorio](./README.md) · Producto: [triage-reserva-turno.md](../../../producto/triage-reserva-turno.md) · [recorrido-pre-post-consulta.md](../../../producto/recorrido-pre-post-consulta.md)

## De qué se trata

Paciente con **turno de medicina clínica / generalista**: control, síntoma leve programable (banda C/D) o seguimiento ambulatorio tras derivación. Incluye el **recorrido pre-consulta** (intake opcional, chat de motivos, cohorte) y la **atención del día** con captura clínica.

**Intent principal (reserva):** `atencion.necesito-atencion`  
**Encounter:** AMB, turno presencial o teleconsulta.

---

## Prerrequisitos QA

| Requisito | Notas |
|-----------|--------|
| Usuario paciente con sector/provincia | [contexto-registro.md](../../paciente/contexto-registro.md) |
| Efector con servicio **Medicina clínica** y agenda con cupos | Hub paciente: solo generalistas en reserva directa |
| Usuario médico (PES) con turno asignado al paciente de prueba | Misma fecha que la reserva |
| Notificaciones push activas en el celular del paciente | Para recordatorios journey |
| Opcional: `motivos_consulta_intake.yaml` con `enabled: true` | Si probás intake previo al chat |
| Opcional: `care_cohort.enabled` en frontend | Si probás cuestionario pre-consulta por cohorte |

---

## Consulta de ejemplo (guion)

### Lo que dice el paciente (app / asistente)

**Al reservar (asistente):**

> «Tengo un poco de tos desde hace tres días, sin fiebre. Quiero un turno con el médico clínico.»

**En motivos pre-consulta (chat, 48–72 h antes del turno):**

> «La tos es seca, más por la noche. No tuve fiebre. Tomé solo miel. No tengo dificultad para respirar.»

**Si hay intake previo (formulario):**

- Motivo principal: *Síntoma o malestar nuevo*
- Desde cuándo: *3 días*
- Intensidad: *2 — leve*

### Lo que dice el médico (en consultorio / captura)

Dictado o texto en captura clínica:

> «Paciente de 34 años, consulta por tos seca de tres días de evolución, sin fiebre ni disnea. Examen: buen estado general, auscultación sin ruidos agregados. Impresión: cuadro viral upper respiratory. Indicaciones: hidratación, analgesia si molesta, control si empeora o fiebre.»

---

## Paciente — paso a paso

### 1. Reservar turno

**Intent:** `atencion.necesito-atencion` (Atajo **Atención**)

1. **Vos** abrís **Asistente** y describís el malestar leve (frase del guion).
2. **El sistema** muestra triage: motivo → alarmas → zona → evolución (sin banda A).
3. **Vos** elegís servicio **Medicina clínica**, centro, profesional y horario.
4. **El sistema** confirma el turno en **Inicio** → próximos turnos.

### 2. Preparar la consulta (journey)

Cuando falten **≤ 72 h** para el turno (ventana por defecto):

1. **Vos** entrás al turno desde **Inicio** o **Mis turnos** → **Preparar tu consulta** (hub journey).
2. **El sistema** lista fases habilitadas: intake (si está activo), motivos de consulta, pre-consulta cohorte (si aplica).
3. **Vos** completás intake (si corresponde) y el **chat de motivos** con el guion.
4. **El sistema** guarda mensajes; el resumen IA se genera al **cerrar la ventana** (~2 min antes del turno).

### 3. Día del turno

1. **Vos** recibís recordatorios push si el cron los encoló al crear el turno.
2. **Vos** podés confirmar asistencia: *«confirmo que voy»* (`turnos.confirmar-asistencia-flow`).
3. Tras la atención, si hay pack followup, **el sistema** puede ofrecer **seguimiento post-consulta** en el hub (hasta +30 días).

---

## Personal de salud — paso a paso

### 1. Antes del turno

1. **Vos** (médico) abrís **Pacientes del día** o agenda con sesión ambulatoria.
2. **El sistema** lista el turno del paciente de prueba.

**Demasiado pronto (opcional):**

1. **Vos** intentás abrir historia clínica muchas horas antes.
2. **El sistema** puede responder `HC_ANTES_DE_VENTANA` hasta la ventana médico (~1 min antes por defecto).

### 2. Pre-consulta en timeline

1. **Vos** abrís timeline del paciente con `turno_id` en contexto (web o app Personal de Salud).
2. **El sistema** muestra en orden:
   - Preguntas previas al chat (si hubo intake),
   - Resumen de motivos (chat/IA),
   - Asistencia pre-consulta cohorte (si aplica),
   - Signos vitales.

### 3. Atender y documentar

1. **Vos** iniciás atención desde el turno ([medico/captura-clinica.md](../../medico/captura-clinica.md)).
2. **Vos** dictás o escribís el guion del médico.
3. **El sistema** propone borrador; **vos** revisás y **Guardar**.
4. **El sistema** finaliza el encounter; puede programar notificaciones post-consulta (`JOURNEY_POSTCONSULTA_*`).

---

## Notificaciones — cuándo esperar qué

| Momento (referencia) | Quién | Qué esperar | Notas |
|---------------------|-------|-------------|--------|
| Al confirmar reserva | Paciente | Push de confirmación de turno | Según configuración del efector |
| Tras crear turno (cron) | Paciente | Recordatorios journey (`JOURNEY_*`) hacia apertura de ventana motivos | Anchor `turno_start`; depende de `encounter_phase_windows.yaml` |
| 72 h antes (aprox.) | Paciente | Aviso para preparar consulta / motivos | Deep link con `id_turno` y `phase` |
| Cierre ventana motivos (~2 min antes) | — | Batch IA: resumen en `reason_text` | No es push; el médico lo ve en timeline |
| 1 min antes del turno (aprox.) | Médico | Historia clínica disponible | Ventana `historia_clinica_apertura_medico_minutos` |
| Tras finalizar atención | Paciente | Push post-consulta / touchpoints followup | Si hay pack y fase `post_consulta` habilitada |
| Resumen de atención listo | Paciente | Aviso de resumen en lenguaje claro | [resumen-atencion-paciente.md](../../../producto/resumen-atencion-paciente.md) |

En staging, el cron puede no correr en tiempo real: pedí al responsable ejecutar `php yii turno-notificacion/run` o confirmar intervalo.

---

## Qué validar (checklist)

| ID | Validación |
|----|------------|
| MG-01 | Reserva completa sin banda A; turno visible en app e agenda médico |
| MG-02 | Hub «Preparar consulta» aparece dentro de ventana de motivos |
| MG-03 | Chat de motivos guarda mensajes; resumen visible en timeline staff tras cierre de ventana |
| MG-04 | Intake previo (si habilitado) visible en timeline antes del resumen |
| MG-05 | Cohorte pre-consulta (si habilitada) visible en timeline staff |
| MG-06 | Captura guarda encounter; paciente ve atención en historial |
| MG-07 | Al menos un push journey o post-consulta recibido (o encolado verificado en BD/logs) |

---

## Referencias

- Ambulatorio remoto: [teleconsulta.md](./teleconsulta.md)
- QA paciente: [turnos.md](../../paciente/turnos.md)
- QA médico: [captura-clinica.md](../../medico/captura-clinica.md) · [turnos.md](../../medico/turnos.md)
- QA staff: [turnos-agenda.md](../../staff/turnos-agenda.md) · [notificaciones-automaticas.md](../../staff/notificaciones-automaticas.md)
- App Personal de Salud: [APS-07](../../app-personalsalud/README.md)
