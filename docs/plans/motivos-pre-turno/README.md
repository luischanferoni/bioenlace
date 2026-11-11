# Motivos pre-turno — sin STT, deducidos y con cuestionario

## Objetivo

Que el médico reciba, antes del turno, un motivo de consulta que **no dependa de que el paciente
escriba en un chat libre ni grabe audios**:

1. **Sin STT en motivos.** El audio y la transcripción salen del flujo de motivos de consulta
   (`AppointmentReasonMessageService`, `AppointmentReasonBatchService`, app paciente). El dictado
   de la captura clínica del staff no se toca.
2. **Motivo deducido de lo que el paciente ya contó.** El resumen se arma con la trayectoria del
   triage de reserva (`Turno::reserva_triage_code`, `reserva_triage_meta_json`, nota libre) y con
   los mensajes que el paciente escribió al asistente **antes** de pedir el turno
   (`asistente_interaccion`).
3. **Cuestionario pre-turno en lugar de chat libre.** Un bot hace preguntas establecidas con
   opciones; el paciente no escribe libremente salvo en la opción «Otra» de cada pregunta y en el
   cierre final (límite **320 caracteres**). No se vuelve a preguntar lo que el triage ya respondió.

## Estado actual (medido)

| Pieza | Dónde | Situación |
|-------|-------|-----------|
| Chat libre de motivos | `EncounterReasonsChatScreen`, `encounter-reasons/mensajes · enviar · subir` | Texto + imagen + audio; mensajes en `interaccion_motivos_consulta` |
| STT del flujo | `DeviceSpeechDictation`, `SttClientConfig`, `SpeechToTextManager::transcribirLote`, `DeviceSttQualityAssessor` | Dictado en dispositivo + transcripción por lote de los audios |
| Resumen para el médico | `AppointmentReasonBatchService` (una IA al cierre de ventana) | Concatena los mensajes; escribe Condition CC + `ia_clinical_suggestions` |
| Guía declarativa del chat | `AppointmentReasonChatGuideCatalog`, `encounter-journey/motivos-intake` | Preguntas **en texto**, sin respuestas persistidas (`submitIntake` rechaza envíos) |
| Ventana y elegibilidad | `AppointmentReasonWindowService`, `EncounterPhaseWindowsCatalog`, `EncounterPhaseEligibilityCatalog` | Cierra `motivos_consulta_cierre_minutos` (10) antes del turno |
| Recordatorios | `encounter-journey-notifications.yaml`, `JOURNEY_MOTIVOS_RECORDATORIO`, `JOURNEY_MOTIVOS_ULTIMO_AVISO` | Push con `id_turno` + `phase`; abre el hub de preparación |
| Triage de reserva | `TurnoReservaTriageDraftService`, `ReservaTriageCatalog` | `triage_raiz`, `triage_zona`, `triage_alarmas`, `triage_evolucion`, `triage_detalle`, nota libre |
| Conversación del asistente | `asistente_conversacion` / `asistente_interaccion` | Persistida por usuario; hoy no la lee nadie en motivos |

## Decisiones

| # | Decisión |
|---|----------|
| 1 | El cuestionario **reemplaza** el chat libre de motivos. El care pack (`asistencia_pre_consulta`) es otra fase y no se toca. |
| 2 | Preguntas y opciones viven en un **catálogo PHP de dominio**, igual que `AppointmentReasonChatGuideCatalog`. No se editan prompts YAML. |
| 3 | La selección de preguntas depende del triage: `only_when` (zona/raíz) y `skip_when_triage_fields` (ya respondido). |
| 4 | Origen del motivo deducido: **triage de reserva + mensajes al asistente previos al alta del turno**. |
| 5 | Cierre libre con **320 caracteres**. «Otra» por pregunta con límite propio, más corto. |
| 6 | El motivo clínico sigue siendo **un solo resumen al cierre de ventana** (`motivos_ia_processed_at`), no una llamada de IA por respuesta. |

## Arquitectura propuesta

| Capa | Pieza nueva / modificada |
|------|--------------------------|
| Metadata / catálogo | `AppointmentReasonQuestionnaireCatalog` (preguntas, opciones, `only_when`, `skip_when_triage_fields`, cierre libre) |
| Motor | `AppointmentReasonQuestionnaireCatalogService` (selección según triage) y `AppointmentReasonQuestionnaireService` (estado, respuesta, cierre) |
| Dominio | `AppointmentReasonInitialContextService` (triage + asistente) |
| Persistencia | Tabla `interaccion_motivos_cuestionario` + `EncounterReasonQuestionnaireAnswer` |
| API | `encounter-reasons/cuestionario` y `encounter-reasons/responder-cuestionario` |
| Resumen | `AppointmentReasonBatchService` compone triage + asistente + respuestas + textos sueltos |
| App paciente | Pantalla de cuestionario en lugar de chat; sin micrófono ni STT |
| Staff | Respuestas del cuestionario junto al resumen en la historia clínica |

## Fases

| Fase | Qué | Estado |
|------|-----|--------|
| [01 — Backend del cuestionario](phases/01-backend-cuestionario.md) | Catálogo, tabla, servicios, endpoints, deducción y batch sin STT | Implementada |
| [02 — App paciente](phases/02-app-paciente.md) | Pantalla del cuestionario, sin audio; deep link desde push y desde el turno | Implementada |
| [03 — Vista staff](phases/03-vista-staff.md) | Respuestas y origen del motivo en historia clínica / timeline | Implementada |
| [04 — Baja de audio](phases/04-baja-de-audio.md) | Retiro de `subir` audio, `pending_motivo_message` y endpoints sin uso | Implementada |
| [05 — Notificaciones y cierre](phases/05-notificaciones-y-cierre.md) | Copy de push, deep link con acción, volcado a producto y borrado del plan | Implementada |

## Criterio de cierre

- Ningún camino de motivos de consulta invoca STT (ni en dispositivo ni en servidor).
- El resumen del médico se genera aunque el paciente **no haya escrito nada**: alcanza con el
  triage de reserva.
- El paciente no puede enviar texto libre en las preguntas; sólo opciones, «Otra» acotada y el
  cierre de 320 caracteres.
- Una pregunta cuya respuesta ya está en el triage no se vuelve a mostrar.
- `AppointmentReasonChatGuideService::submitIntake` deja de ser un rechazo explícito: el
  cuestionario es la vía de respuesta.

## Coordinación de release

La fase 01 es compatible hacia atrás: el chat y `subir` siguen respondiendo para las apps ya
publicadas. El micrófono se retira en la fase 02 y la aceptación de audio en la 04, en ese orden.

## Dependencias

Ninguna con otros planes. La fase 03 comparte superficie con
[captura-clinica.md](../../producto/captura-clinica.md); la 05 con
[recorrido-pre-post-consulta.md](../../producto/recorrido-pre-post-consulta.md).
