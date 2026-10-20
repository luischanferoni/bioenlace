# Fase 02 — Terminología «encounter»

## Objetivo

Que «encounter» sea el único término en código, rutas, clases y tablas. «Consulta» solo queda en copy español que decida el backend.

## Tareas

- Glosario en `web/docs/producto/` con los términos oficiales (encounter, episodio, captura, motivo) y su equivalente en copy. Frontera fijada: «consulta» solo en lo visible; «encounter» en todo lo interno; «atención» no se usa para este concepto.
- Backend: renombrar las 37 clases con «Consulta» en el nombre. Familias de rutas a renombrar: chat de consulta, motivos de consulta, consultas de seguimiento, ver consulta como staff, consulta async.
- Borrar las rutas viejas sin alias.
- Migraciones nuevas para tablas y columnas que lo exijan, sin vistas de compatibilidad.
- Móvil: clases, archivos, métodos y rutas de las dos apps.
- Web: JS, vistas, assets, claves de configuración.
- Textos visibles: pasan al catálogo de mensajes del backend.
- Prompts y YAML del asistente alineados al glosario.

## Riesgos

Despliegue coordinado de backend, web y apps. Las apps instaladas dejan de funcionar hasta actualizarse.

## Criterio de salida

El contador de «consulta» en código llega a cero y solo se permite en copy declarado por el backend.
