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
