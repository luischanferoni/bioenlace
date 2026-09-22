# Turnos

## De qué se trata

Un **turno** es la cita **ambulatoria (AMB)** entre una persona y un profesional en un efector y un **servicio de salud del centro**: fecha, hora, estado (pendiente, cancelado, atendido, en resolución…) y reglas de **autogestión** para el paciente (reservar, cancelar, reprogramar con anticipación mínima).

El `id_servicio` del turno es la **oferta institucional** (área que el efector brinda), no la especialidad del título del profesional ni un acto SNOMED. Ver [glosario-servicio-pes-acto.md](./glosario-servicio-pes-acto.md).

La grilla de cupos es solo `encounter_class = AMB`. Guardia e internación usan **horario de presencia**, no turnos de paciente — ver [agenda-por-encounter-class.md](./agenda-por-encounter-class.md).

## Actores

- **Paciente:** reserva y gestiona citas desde Bioenlace.
- **Tutor o representante:** puede reservar y gestionar turnos **de otro paciente** (menor sin cuenta o adulto que delegó), fijando `subject_persona_id` o el contexto «A cargo de» en móvil. Ver [representacion-paciente.md](./representacion-paciente.md).
- **Profesional y administración del efector:** calendario, alta para terceros, sobreturnos, cancelación masiva de un día.
- **Staff (admisión / enfermería):** alta de persona vía asistente (lector DNI / Didit); no implica fijar paciente en la sesión operativa del staff — ver [registro-paciente.md](./registro-paciente.md).
- **Administrativo:** alta de turno para un paciente desde el asistente / agenda del efector. No hay UI de “ventanilla” (identificar y operar como el paciente). Ver [representacion-paciente.md](./representacion-paciente.md).
- **Sistema:** recordatorios y avisos cuando cambia la agenda o el turno entra en conflicto (**push**; WhatsApp utility **no** habilitado — ver [notificaciones](#notificaciones-push-y-whatsapp)).

## Cómo funciona (reserva paciente)

```mermaid
flowchart TB
  P[Paciente en Bioenlace]
  A[Interfaz: conversación o pantalla directa]
  T[Triage motivo y alarmas]
  API[API turnos / agenda]
  AG[Grilla PES y cupos]
  DB[(Turno en base)]
  PUSH[Notificación push]
  P --> A
  A --> T
  T --> API
  API --> AG
  AG --> DB
  DB --> PUSH
  PUSH --> P
```

1. **Triage breve** (motivo, alarmas, zona/evolución según el caso): catálogo fijo + UI JSON; si hay **banda A** no se sigue con la reserva en la app. Detalle: [triage-reserva-turno.md](./triage-reserva-turno.md).
2. El paciente elige **servicio del centro** (área/oferta); si el caso y el servicio lo permiten, **modalidad** (presencial o teleconsulta); luego **centro, profesional y horario** (flujo asistente `atencion.necesito-atencion`; `turnos.crear-como-paciente` solo agenda si ya nombró oferta/profesional). Pedido bare (*«quiero un turno»*) → incompletas + CTAs. En el camino “estudio o práctica” primero elige el **acto** (SNOMED) y luego el servicio institucional que lo realiza. Elegibilidad remota: [teleconsulta-elegibilidad.md](./teleconsulta-elegibilidad.md). Glosario: [glosario-servicio-pes-acto.md](./glosario-servicio-pes-acto.md).
3. La API consulta **disponibilidad** alineada a la agenda AMB del profesional (PES = asignación a ese servicio del efector).
4. Al confirmar, se **persiste** el turno (incluye `reserva_triage_*` y `urgency_band`) y puede dispararse confirmación o recordatorio.
5. Tras la reserva, los **motivos pre-consulta** (intake opcional, chat/IA, cohorte) enriquecen el encounter hasta el turno — ventanas, journey y **vista staff** en historia clínica: [recorrido-pre-post-consulta.md](./recorrido-pre-post-consulta.md).
6. Si el efector **cambia la agenda**, los turnos afectados pueden pasar a **en resolución** y el paciente recibe **push** para reubicar o cancelar.

## Cancelación y reprogramación

- El paciente solo puede actuar dentro de ventanas configuradas (horas de anticipación).
- El médico o staff puede cancelar con otro alcance de permisos.
- La política evita huecos imposibles y mantiene trazabilidad del cambio.

## Indicadores de agenda (staff)

Para **dirección y coordinación** del efector, el equipo puede consultar métricas de acceso sin exportar planillas:

- **No-show:** turnos `SIN_ATENDER` atribuibles al paciente en el período.
- **Tasa de no-show** sobre turnos cerrados (atendidos + ausentes).
- **Lead time:** mediana y promedio de días entre la **fecha de reserva** y la **fecha de la cita**.

Superficies: API `GET /api/v1/turnos/indicadores-agenda` (filtros por período y PES); intent de asistente `turnos.indicadores-agenda-flow` (UI JSON embebida).

## Perfil histórico de turnos

Bioenlace materializa un **perfil factual** longitudinal a partir del stream canónico de eventos de turno (asistencia, no-show, cancelación, reprogramación, confirmación). No es una reputación ni un score comercial: describe hechos versionados, por ventanas (90/180/365 días) y alcances (global, efector, servicio, modalidad).

- Preferencias declaradas (`persona_agenda_preferencias`) permanecen separadas del comportamiento observado.
- Las políticas (anti no-show A04, autogestión de cancelaciones) siguen decidiendo con reglas propias; en **shadow** adjuntan un candidato factual comparable (`diff_reason`) sin cambiar el desenlace.
- Liberación automática de cupos permanece **deshabilitada** (`execution_mode: shadow`, `release_slot.enabled: false`) hasta evaluación operativa.
- La persona puede consultar historial/explicación y solicitar corrección; el staff ve agregados y resuelve correcciones.
- Operación: `php yii turno-behavior-profile/materialize`, `rebuild`, `coverage`.

La falta de historial se trata como información insuficiente, no como conducta de riesgo.

## Adelantamiento por cancelación (agente A03)

Cuando un turno se **cancela** y el slot queda libre con al menos **24 h** de anticipación, el sistema puede ofrecer **adelantar** turnos posteriores compatibles (mismo efector, servicio, PES y modalidad). El slot permanece **público** (sin hold); la reserva normal compite con la aceptación.

1. Tras la cancelación, el agente `turno-advance-offer` elige candidatos en la **misma franja** (mañana &lt; 13:00 / tarde ≥ 13:00): primero **D+2** en orden horario, luego **D+1**; no ofertado el mismo día. Días calendario (si no hay agenda el finde, no hay candidatos). Sólo con push activo.
2. Envía una oferta secuencial (`TURNO_ADVANCE_OFFER`, acción `adelantar_turno`) con texto “sujeto a disponibilidad”; espera **2 h** por candidato y no envía nuevas ofertas desde **T−6 h**.
3. El paciente **acepta** con `POST …/adelantar-oferta-como-paciente` (`offer_token`); se **reprograma** el turno existente (no se crea uno nuevo). Una aceptación cierra la campaña; el horario que deja libre no dispara otra campaña.
4. El cron `yii turno-advance-offer/run` avanza campañas vencidas; `yii turno-advance-offer/repair` recupera cancelaciones elegibles sin campaña.

Flag: `autonomous_agent_advance_offer_enabled`. Metadata: `agents/turno-advance-offer.yaml`. Detalle: [agentes-autonomos.md](./agentes-autonomos.md).

## Escalada multicanal (agente A02, v1)

Si el paciente no responde al push de reubicación dentro del plazo configurado (24 h por defecto), el agente `turno-resolucion-multicanal` escala a **email** y luego **SMS** (stub en v1: log + mailer si está disponible).

1. Al marcar un turno `EN_RESOLUCION` y enviar push, se programa `RESOLUCION_MULTICANAL` en `turno_notificacion_programada`.
2. El cron `yii turno-notificacion/run` ejecuta el agente: genera **link firmado** y lo incluye en el mensaje.
3. La página pública `/turno/resolucion/{token}` muestra el turno y un botón hacia la app para reubicar.

Parámetros: `turnoResolucionMulticanal` (`public_base_url`, `app_deep_link`, `signing_key`). Flag: `autonomous_agent_resolucion_multicanal_enabled`.

## Cierre de loop (agente A06, v1)

Si tras **72 h** (configurable) el turno sigue en `EN_RESOLUCION` sin reubicar:

1. El agente `turno-resolucion-loop-close` evalúa reglas YAML.
2. **Default:** cancela el turno, notifica al paciente (`TURNO_RESOLUCION_SIN_RESPUESTA`) y libera el cupo (puede disparar adelantamiento A03 si aplica).
3. **Banda C/D:** escala a staff del PES (`TURNO_RESOLUCION_STAFF_ESCALATE`) y mantiene la resolución abierta.

Flag: `autonomous_agent_resolucion_loop_close_enabled`.

## Anti no-show basado en reglas (agente A04, v1)

Un no-show deja un hueco que otro paciente podría haber usado. A04 intenta **anticiparse**: no espera a la ausencia, sino que, según el historial reciente, pide confirmación o recuerda el turno a quienes concentran más riesgo.

Qué **sí** hace:

- Clasifica el turno en `low` / `medium` / `high` con reglas fijas (ausencias previas, anticipación de la reserva, primera visita).
- Programa puntos de contacto antes de la cita (confirmación ~48 h antes si es high; recordatorio ~2 h antes si es medium o high).
- Deja rastro auditable de la decisión y, en paralelo, un candidato calculado desde el [perfil factual](#perfil-histórico-de-turnos) para comparar —sin que ese candidato mande todavía.

Qué **no** hace:

- No usa machine learning ni un “score de reputación” que etiquete a la persona.
- No bloquea el derecho a atenderse ni baja prioridad clínica.
- No libera el cupo de forma automática en la configuración actual (esa acción existe en la política pero está apagada; ver línea de tiempo abajo).

El agente `turno-antinoshow` se engancha al **crear o reprogramar** un turno pendiente: calcula el nivel, agenda los checkpoints y deja que el cron de notificaciones los ejecute cuando llegue la hora.

### Cómo se estima el riesgo (v1)

Reglas fijas sobre el historial en BD (ventana típica ~6 meses), no sobre un modelo entrenado:

| Señal | Efecto típico |
|-------|----------------|
| Dos o más no-shows atribuibles al paciente | **high** |
| Un no-show, o mucha anticipación reserva→cita (≥ ~21 días) | **medium** (o high si ya hay más ausencias) |
| Primera visita en ese efector | **medium** por defecto |
| Resto | **low** |

En paralelo, el sistema lee el **perfil factual** persistido (si hay snapshot) y arma un *candidato* con el mismo tipo de riesgo. Hoy ese candidato **no cambia** la acción: solo se guarda en auditoría (`diff_reason`: coincide o no con el cálculo legacy). Ver [perfil histórico](#perfil-histórico-de-turnos).

### Línea de tiempo del turno

Al programar el turno se encolan notificaciones (`turno_notificacion_programada`). El cron `yii turno-notificacion/run` las dispara:

1. **T−48 h (solo riesgo high).** Se evalúa la confirmación compartida con el flujo base de “confirmá asistencia”: un push de confirmación explícita (`TURNO_ANTINOSHOW_CONFIRM`), no un segundo mensaje distinto al de confirmación ordinaria. Si la política prevé liberación, también deja programado un chequeo a T−24 h (hoy ese chequeo no libera; ver abajo).
2. **T−24 h — liberación de cupo (apagada).** Pensada solo para **high** y sin confirmación: cancelar el turno como **sistema**, avisar al paciente y emitir el evento `SYSTEM_SLOT_RELEASED` (para que el perfil no lo cuente como cancelación del paciente). Hoy hace falta **las dos** condiciones y ambas están en no: `execution_mode: shadow` y `release_slot.enabled: false` en `TurnoAntinoshowAgentPolicy`. Aunque el agente esté encendido, **no** libera cupos.
3. **T−2 h (riesgo medium o high).** Recordatorio (“tu turno es pronto”) si aún no confirmó / sigue pendiente.
4. **Confirmación entregada / abierta.** Solo cuenta ACK autenticado de la app paciente. Aceptar el HTTP de FCM o mirar la bandeja **no** acredita entrega ni apertura; sin ACK no se interpreta “no confirmó” como culpa del paciente.

### Ejemplo breve

Ana tiene dos ausencias recientes → **high**. Reserva un turno para el viernes 10:00. El miércoles (~T−48) recibe el push de confirmación. El jueves (~T−24) **no** se cancela el turno (liberación deshabilitada). El viernes temprano (~T−2) puede recibir el recordatorio si sigue sin confirmar. Todo queda en `agent_run` con el riesgo legacy y el candidato del perfil.

Bruno sin ausencias pero con reserva a 30 días → **medium**: puede recibir recordatorio T−2; no entra en la rama de confirmación extra ni en liberación.

### Operación

- Encendido global: `autonomous_agent_antinoshow_enabled` en params.
- Umbrales, checkpoints y textos: `TurnoAntinoshowAgentPolicy` (PHP de plataforma; no hay switch por efector).
- Ficha técnica: [agentes-autonomos.md](./agentes-autonomos.md) (A04).


## Notificaciones: push y WhatsApp

Hoy los agentes de turno despachan **push** (FCM). WhatsApp Cloud API en producto:

| Canal | Rol | Estado |
|-------|-----|--------|
| Chat asistente (paciente escribe) | Misma capacidad que app | **Habilitado** (Meta service ≈ $0) |
| Avisos proactivos (recordatorio, anti no-show, resolución, adelantamiento, etc.) | Equivalente al push | **No** por WhatsApp (utility **no habilitada**); siguen push / escalada email-SMS |

Detalle de COGS: [costos-api §7](../costos/costos-api.md#7-whatsapp-cloud-api-paciente). Escalada multicanal v1: [§ Escalada](#escalada-multicanal-agente-a02-v1).

## Shortlist en resolución (agente A01 v1 D1)

Cuando un turno entra en `EN_RESOLUCION`, el agente `turno-resolucion-shortlist` busca candidatos (horarios vecinos + slots disponibles), los puntúa y adjunta hasta **3 opciones** al push (`shortlist` en payload FCM).

El paciente confirma una opción con `POST …/elegir-shortlist-resolucion-como-paciente` (`id_turno`, `option_id` como `sl_0`…). Si no elige del shortlist, sigue disponible la grilla completa.

Flag: `autonomous_agent_resolucion_shortlist_enabled`.

## Auto-reserva en resolución (agente A01 v1 D2)

Antes del push de reubicación, si el paciente tiene **opt-in** (`auto_reserva_resolucion`) y el efector habilitó la política (`efector_turnos_config.auto_reserva_resolucion_habilitada`), el agente `turno-resolucion-auto-reserva` intenta elegir **un** slot unívoco según preferencias (franjas, días, modalidad, mismo PES prioritario).

Si hay candidato con score y brecha suficientes, persiste la reprogramación y envía push `TURNO_AUTO_REUBICADO_RESOLUCION` (opt-out: reprogramar en app). Si no, continúa el flujo shortlist + multicanal.

Preferencias: `GET|POST …/preferencias-agenda-como-paciente` (`auto_reserva_resolucion`, `franjas`, `dias_semana`, `tipo_atencion_preferido`, `mismo_pes_prioritario`).

Flags: `autonomous_agent_resolucion_auto_reserva_enabled` (global) + columna efector.

## Citas desde NIS (FHIR externo)

Efectores que publican agenda en el **NIS MSAL** pueden tener un **espejo** de `Appointment` en Bioenlace: el staff vincula cada `Schedule` HAPI con un PES local; un job trae citas nuevas o modificadas y, si está habilitado, las cancelaciones o cierres en Bioenlace actualizan `Appointment.status` en NIS.

- No reemplaza la reserva paciente nativa cuando el efector opera solo con grilla Bioenlace.
- Turnos espejo pueden existir sin paciente local hasta resolver DNI/CUIL.
- Detalle operativo y confianza PES: [interoperabilidad-agendamiento-fhir.md](./interoperabilidad-agendamiento-fhir.md).

## Relación con el resto del producto

- Representación operativa (tutela/delegación): [representacion-paciente.md](./representacion-paciente.md).
- Integración NIS HAPI (citas externas): [interoperabilidad-agendamiento-fhir.md](./interoperabilidad-agendamiento-fhir.md).
- Un turno puede originar un **encounter** ambulatorio al atenderse (captura clínica).
- Los turnos también se pueden iniciar por conversación; el detalle técnico del motor está en [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md).
- Madurez HIS del módulo: [his-completo/11-agenda-turnos.md](../his-completo/11-agenda-turnos.md).

## Fuera de este documento

Facturación del acto, contenido clínico del encuentro y RRHH puro sin cita agendada.
