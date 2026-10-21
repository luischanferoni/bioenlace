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

En curso. El prompt YAML de Guide ya incorpora las distinciones del glosario clínico (encounter, episodio, captura y motivo) y restringe su exposición al usuario: usa el copy recibido en botones/pantallas y evita «encounter»/«atención» como sustitutos de «consulta». La documentación de producto enlaza esta regla con el glosario y una prueba de metadatos verifica que las definiciones estén presentes en el prompt. Los títulos y cuerpos de notificaciones push del journey clínico se centralizaron en `Encounter/Application/Catalog/encounter-journey-notifications.yaml`; el scheduler y el cron leen el mismo catálogo, incluso para filas antiguas que carecen de texto en su payload. El fallback del título de siguiente touchpoint también usa el catálogo. La guía de turnos documenta este source of truth.

Pendiente: revisar los mensajes de sistema y flujos YAML restantes; validar que las pantallas nuevas tengan una acción catalogada que el runtime pueda enriquecer a `client_open`; revisar terminología/copy del panel administrativo MVC, mensajes de jobs/agentes fuera del journey de turnos, documentación estable y reglas `.cursor`.
