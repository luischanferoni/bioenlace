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

Se centralizaron en `BioenlaceApiClient` el rótulo de botón, el estado vacío y el error repetidos por los widgets de descarga PDF de laboratorio y receta. El estado vacío que antes se construía como HTML ahora usa un elemento DOM. Se conservó el copy; el contador `web_js_literal_ui_text` baja de 66 a 62 (tope versionado: 65) y `web_js_html_concat` de 226 a 224 (tope: 226). El asset compartido del cliente API se registra como dependencia de `AppAsset` antes de ambos widgets. La prueba focalizada de inicialización detectó que el widget de receta invocaba su IIFE sin pasar el objeto global; se corrigió la invocación y se verificó que ambos widgets representen los estados vacío y con botón.

Se eliminó un bloque comentado y sin consumidor que aún contenía la implementación HTML anterior del panel de última vacuna. No cambia el runtime y redujo el contador `web_js_html_concat` de 224 a 222.

Los widgets de preview de impacto de licencias y de configuración de agenda pasaron de construir markup concatenado a crear nodos DOM y asignar datos dinámicos mediante `textContent`. Se mantienen los mensajes, clases, fechas, límites de resultados, estados de carga/error y las modalidades de consulta automática o manual; los datos recibidos no se interpretan como markup. El hint de duración de licencia también monta su elemento con DOM en lugar de `insertAdjacentHTML`; conserva la actualización ante eventos `change` e `input`. En el renderizador UI JSON, los cargadores de contenido y los flujos de búsqueda de la SPA, las alertas de respuesta inválida, bloques no soportados, errores y estados de carga ahora usan nodos DOM con texto separado. Los errores incluyen URL y mensaje como texto, no como markup. La medición actual es `web_js_html_concat` 164/226, `web_js_literal_ui_text` 62/65 y `web_views_logic` 39/39. Los topes medidos están satisfechos, pero no se da por cerrada la tarea Camino A: aún resta revisar HTML dinámico en JS no cubierto por el contador, además de los otros entregables de la fase.
