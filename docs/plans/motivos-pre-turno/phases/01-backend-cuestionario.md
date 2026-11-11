# Fase 01 — Backend del cuestionario y motivo deducido

Estado: **implementada** (2026-11-11). Falta correr las migraciones en un entorno con MySQL.

## Alcance

Backend completo, sin cambios de app: el cuestionario se puede renderizar y responder por API, el
resumen del médico se arma igual que hoy pero con más fuentes y sin STT.

## Entregables

| # | Entregable | Estado |
|---|------------|--------|
| 1 | Catálogo `AppointmentReasonQuestionnaireCatalog`: preguntas, opciones, `allow_other`, `only_when`, `skip_when_triage_fields` y cierre libre de 320 caracteres | Hecho |
| 2 | `AppointmentReasonQuestionnaireCatalogService`: selecciona preguntas según el triage del turno y descarta las ya respondidas | Hecho |
| 3 | Tabla `interaccion_motivos_cuestionario` (una fila por pregunta respondida) y modelo `EncounterReasonQuestionnaireAnswer` | Hecho (migración sin ejecutar) |
| 4 | `AppointmentReasonQuestionnaireService`: estado, respuesta, cierre y serialización para el resumen | Hecho |
| 5 | `AppointmentReasonInitialContextService`: trayectoria del triage con etiquetas + nota libre + mensajes del paciente al asistente anteriores al alta del turno | Hecho |
| 6 | Endpoints `encounter-reasons/cuestionario` (GET) y `encounter-reasons/responder-cuestionario` (POST) con RBAC heredado de `encounter-reasons/listar-mensajes` | Hecho |
| 7 | `AppointmentReasonBatchService` sin STT y con las fuentes nuevas; el resumen se genera aunque no haya mensajes de chat | Hecho |
| 8 | Tests unitarios del catálogo y de la selección por triage | Hecho |

## Reglas de la selección de preguntas

- Una pregunta con `only_when` se incluye sólo si el triage del turno matchea **todas** las claves
  declaradas (`triage_raiz`, `triage_zona`, y las que se sumen al catálogo).
- Una pregunta con `skip_when_triage_fields` se omite si el triage ya tiene alguno de esos campos:
  el paciente ya lo contó al reservar.
- Una pregunta con respuesta persistida no se vuelve a mostrar; se devuelve en `answered` con la
  opción elegida.
- El cierre libre se muestra una sola vez, al final, y es lo único obligatorio para considerar el
  cuestionario completo.

## Deducción del motivo

Orden de composición del texto que consume la IA del lote:

1. Triage de reserva: etiquetas de cada código de `reserva_triage_meta_json` (incluida la nota
   libre del paciente).
2. Mensajes del paciente al asistente anteriores al alta del turno, ventana acotada y con tope de
   mensajes; se descartan marcadores de acción (`[action_id:…]`) y textos que son JSON.
3. Respuestas del cuestionario, con el texto de la pregunta y la opción elegida.
4. Mensajes de texto del chat de motivos, sólo como compatibilidad con datos ya cargados.

El prompt no cambia: todo entra por el bloque de mensajes que el servicio ya compone.

## Verificación

- `php -l` de cada archivo tocado: OK.
- `codecept run unit clinical/AppointmentReasonQuestionnaireCatalogServiceTest.php`: OK (7 tests).
- Suites `clinical`, `scheduling` y `platform/core` sin fallas nuevas respecto de la línea base
  (los errores que quedan son de entorno: sin MySQL local y mocks de clases finales).
- `codecept run unit platform/core/ArchitectureHttpUsageTest.php`: OK.
- Pendiente: `php yii migrate --interactive=0` y `migrate/down 1` + `migrate/up 1` en un entorno
  con MySQL (la conexión local no está disponible).

## Fuera de alcance

Pantalla móvil, vista staff, retiro de `subir` audio y copy de notificaciones.
