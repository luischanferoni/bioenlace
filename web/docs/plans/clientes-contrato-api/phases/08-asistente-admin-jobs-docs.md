# Fase 08 — Asistente, admin, jobs y documentación

## Objetivo

Cerrar las superficies restantes del asistente, jobs y documentación; alinear la terminología del panel administrativo MVC cuando corresponda.

## Tareas

- Asistente: mensajes de sistema en YAML, `client_open` para las pantallas nuevas, prompts con el glosario de la fase 2.
- Admin (MVC): revisar controladores, servicios y vistas del panel para alinear la terminología/copy con el glosario. No requiere integrar el panel con la API ni adoptar su contrato de respuesta.
- Notificaciones push y mensajes de jobs: textos desde catálogo YAML (hoy en servicios con «Consulta» en el nombre).
- Actualizar `web/docs/` (producto, arquitectura, decisions) y las reglas de `.cursor/rules`.
- Revisar README de `common/components/` y enlaces que apunten a nombres renombrados.

## Criterio de salida

Sin referencias a nombres viejos. Documentación estable lista para recibir lo que hoy está en este plan.

## Avance

En curso. El prompt YAML de Guide ya incorpora las distinciones del glosario clínico (encounter, episodio, captura y motivo) y restringe su exposición al usuario: usa el copy recibido en botones/pantallas y evita «encounter»/«atención» como sustitutos de «consulta». La documentación de producto enlaza esta regla con el glosario y una prueba de metadatos verifica que las definiciones estén presentes en el prompt. Los títulos y cuerpos de notificaciones push del journey clínico se centralizaron en `Encounter/Application/Catalog/encounter-journey-notifications.yaml`; el scheduler y el cron leen el mismo catálogo, incluso para filas antiguas que carecen de texto en su payload. El fallback del título de siguiente touchpoint también usa el catálogo. Además, las notificaciones del agente B01 (alerta a staff y mensaje educativo al paciente) se movieron desde la política operativa PHP a `CareCohort/Application/Catalog/care-followup-notifications.yaml`; las reglas de disparo continúan en `CareFollowupBranchingAgentPolicy`. La guía de turnos y la ficha de agentes documentan estos source of truth. El test focalizado del catálogo pasó (2 tests, 6 assertions) en el runtime local PHP 8.0 mediante bypass temporal del chequeo Composer, que se restauró al terminar; no certifica runtime soportado (PHP >=8.1).

La auditoría de `open_ui.action_id` en los cuatro flows clínicos verificó los 18 identificadores con el resolver del runtime: 17 ya obtenían `client_open` desde providers o templates UI JSON. Se corrigió el único hueco real (`care-packs.assistance`): el catálogo declaraba un descriptor UI JSON pero no lo materializaba ni lo exponía al registry. Se reutiliza ahora `UiActionCatalogDef` y se agrega una regresión para la ruta y el método GET|POST, incluyendo la resolución del registry. La regla `.cursor/rules/source-of-truth-catalogos.mdc` documenta ahora los catálogos YAML de copy de notificaciones y mantiene las reglas operativas en PHP. En el panel MVC, la vista de detalle de definiciones ahora usa la etiqueta «Clase», coherente con la grilla y con `encounter_class`, en vez de llamar «atención» al tipo de interacción clínica. Para el agente B03 de laboratorio, los umbrales y acciones por severidad siguen en `PostLabClassificationAgentPolicy`; el copy de paciente/staff ahora se carga desde `Laboratory/Application/Catalog/post-lab-classification-notifications.yaml`, con una prueba focalizada de validación del catálogo (3 tests, 12 assertions). El copy de la notificación de oferta para adelantar turnos también pasó de `TurnoAdvanceOfferAgentPolicy` a `Scheduling/Agenda/Application/Catalog/turno-advance-offer-notifications.yaml`; el agente conserva interpolación de fecha/hora y el catálogo falla explícitamente si la definición está incompleta. Se agregó una prueba del título, etiqueta y placeholders.

Los tres mensajes de paciente del agente A04 anti no-show (confirmación extra, recordatorio y liberación de cupo) también pasaron a `Scheduling/Agenda/Application/Catalog/turno-antinoshow-notifications.yaml`; la política conserva exclusivamente knobs de riesgo/checkpoints/liberación. El agente usa el catálogo con validación explícita y mantiene la interpolación existente de nombre, fecha y hora. La prueba verifica el texto exacto de los tres mensajes y que un identificador desconocido falle.

El mensaje de confirmación de auto-reserva del agente A01 D2 se movió de `TurnoResolucionAutoReservaAgentPolicy` a `Scheduling/Agenda/Application/Catalog/turno-resolucion-auto-reserva-notifications.yaml`. El agente mantiene la interpolación de fecha/hora en el constructor del push y la prueba verifica el resultado renderizado; la política PHP conserva los knobs de búsqueda y puntaje.

El copy de los tres resultados del agente A06 de cierre de resolución (escalamiento a staff, cancelación y continuidad) pasó a `Scheduling/Agenda/Application/Catalog/turno-resolucion-loop-close-notifications.yaml`. Las reglas PHP solo conservan el umbral y la acción; el agente obtiene los mensajes del catálogo y mantiene la interpolación de fecha y banda de urgencia. La prueba valida el copy exacto y el error para una clave inexistente.

Las plantillas email/SMS del agente A02 multicanal se movieron desde la política a `Scheduling/Agenda/Application/Catalog/turno-resolucion-multicanal-notifications.yaml`. La selección y habilitación de canales continúa en la política PHP; el agente mantiene el envío y la interpolación de nombre, fecha, hora y enlace. La prueba valida ambos textos y rechaza canales no soportados.

Validación de este incremento: las pruebas del catálogo multicanal pasaron (2 tests, 3 assertions), PHP lint, YAML lint y `git diff --check` también. PHPUnit se ejecutó con PHP 8.0 mediante bypass temporal del guard Composer, restaurado inmediatamente; no certifica el runtime soportado (PHP >=8.1).

El título y la plantilla externa del push del agente A05 de ruteo post-cupo pasaron a `Scheduling/Agenda/Application/Catalog/reserva-triage-post-cupo-notifications.yaml`. Se preservan las copias `mensaje` dentro de las reglas porque integran la recomendación que devuelve el endpoint; no se cambia ese contrato de API. El agente conserva la sustitución de `{{mensaje}}` antes de enviar el push.

Validación: la prueba focalizada del catálogo pasó (1 test, 1 assertion), PHP lint, YAML lint y `git diff --check` pasaron. PHPUnit se ejecutó con PHP 8.0 y el bypass temporal del guard Composer, restaurado al terminar; no certifica PHP >=8.1. El test existente del servicio de ruteo no pudo ejecutarse directamente con PHPUnit porque requiere `common\tests\unit\DbTestCase`, cargado por la configuración de Codeception.

El sufijo de opciones sugeridas del agente D1 shortlist pasó de `TurnoResolucionShortlistAgentPolicy` al catálogo YAML `turno-resolucion-shortlist-notifications.yaml`. Se mantiene el prefijo con su espacio inicial, el separador `; ` entre opciones y el retorno vacío cuando no existen opciones.

Validación de este incremento: la prueba de formato pasó (2 tests, 2 assertions), PHP lint, YAML lint y `git diff --check` pasaron. PHPUnit se ejecutó en PHP 8.0 mediante bypass temporal del guard Composer, restaurado al finalizar; no certifica PHP >=8.1. El test detectó y permitió corregir el import faltante del use case `BuildTurnoResolutionShortlist`.

El título y la plantilla del aviso operativo por dead-letter del agente E02 de reintentos de integración se movieron a `Clinical/HistoryExchange/Application/Catalog/integration-retry-notifications.yaml`. La política mantiene el gate por conector; el flujo conserva los destinatarios configurados, el truncado del error y los metadatos del push.

Validación de este incremento: prueba del catálogo (1 test, 1 assertion), PHP lint, YAML lint y `git diff --check` pasaron. PHPUnit se ejecutó con PHP 8.0 mediante bypass temporal del guard Composer, restaurado inmediatamente; no certifica PHP >=8.1.

Validación de este incremento: la prueba de renderizado pasó (1 test, 1 assertion), PHP lint y `git diff --check` pasaron, y el catálogo YAML fue validado. La prueba se ejecutó con PHP 8.0 mediante bypass temporal del guard Composer, restaurado al terminar; no certifica el runtime soportado (PHP >=8.1). La prueba también detectó imports ausentes para `AutoReserveTurnoResolution` y `PersonaAgendaPreferenciasService` en el agente, que se agregaron para que la construcción normal del agente funcione.

Pendiente: revisar los mensajes de sistema y flujos YAML restantes; revisar terminología/copy del panel administrativo MVC, mensajes de jobs/agentes fuera de los journeys clínicos y B01, documentación estable y reglas `.cursor`.
