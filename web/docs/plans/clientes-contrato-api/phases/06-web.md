# Fase 06 — Web

## Objetivo

La web sigue el mismo criterio que los móviles: renderiza lo que manda la API.

## Tareas

- Eliminar el HTML concatenado en los 33 archivos de `frontend/web/js`; usar plantillas y clonado (Camino A).
- Textos de UI literales: vienen de la API o de un único módulo de infraestructura.
- Cliente HTTP único en JS que interpreta el contrato de errores.
- Vistas PHP: mover a page builders la lógica de request, rol y URL (40 casos) según vistas delgadas.
- Eliminar JS y vistas específicas por tipo de episodio que dupliquen lo que ahora llega por metadata.
- Asistente SPA: confirmar que consume `client_open` sin inferir por URL.
- Guardas de CI para HTML en JS y textos literales.

## Criterio de salida

Contadores de web en su nuevo tope. Pruebas de humo de HC, captura y tablero.

## Avance

La verificación del asistente SPA confirma que el cliente distingue las acciones por `client_open.kind`, sin inferir el tipo de pantalla a partir de la URL: para `ui_json` toma `client_open.api.route`; en tarjetas `native` prioriza el path web declarado y conserva un fallback de ruta si falta; en un paso de flow `native` no intenta montar la pantalla web. Los `intent` se envían al endpoint conversacional con `action_id`. Se conserva el fallback de navegación para payloads legacy que no traen `client_open.kind`. La normalización del sobre cubre la preservación de targets `ui_json` y `native` en `common/tests/unit/platform/assistant/AssistantEnvelopeFlowTest.php`.

Esto completa solo la tarea de verificación del asistente de esta fase; los contadores, el cliente HTTP, las vistas, las plantillas y las pruebas de humo siguen pendientes de validar contra el criterio de salida.
