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

La auditoría de `open_ui.action_id` en los cuatro flows clínicos verificó los 18 identificadores con el resolver del runtime: 17 ya obtenían `client_open` desde providers o templates UI JSON. Se corrigió el único hueco real (`care-packs.assistance`): el catálogo declaraba un descriptor UI JSON pero no lo materializaba ni lo exponía al registry. Se reutiliza ahora `UiActionCatalogDef` y se agrega una regresión para la ruta y el método GET|POST, incluyendo la resolución del registry. La regla `.cursor/rules/source-of-truth-catalogos.mdc` documenta ahora los catálogos YAML de copy de notificaciones y mantiene las reglas operativas en PHP. En el panel MVC, la vista de detalle de definiciones ahora usa la etiqueta «Clase», coherente con la grilla y con `encounter_class`, en vez de llamar «atención» al tipo de interacción clínica. Para el agente B03 de laboratorio, los umbrales y acciones por severidad siguen en `PostLabClassificationAgentPolicy`; el copy de paciente/staff ahora se carga desde `Laboratory/Application/Catalog/post-lab-classification-notifications.yaml`, con una prueba focalizada de validación del catálogo (3 tests, 12 assertions). Se ejecutó con PHP local 8.0 y bypass temporal restaurado del guard Composer; no certifica el runtime soportado por el proyecto (PHP >=8.1).

Pendiente: revisar los mensajes de sistema y flujos YAML restantes; revisar terminología/copy del panel administrativo MVC, mensajes de jobs/agentes fuera de los journeys clínicos y B01, documentación estable y reglas `.cursor`.
