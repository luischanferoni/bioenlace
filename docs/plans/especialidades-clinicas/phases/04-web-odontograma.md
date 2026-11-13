# Fase 04 — Odontograma en la historia web

**Estado:** pendiente.

## Objetivo

Que el odontograma se vea dentro de la historia clínica del paciente en la web, como
interfaz común y no como pantalla del asistente.

## Alcance

- Partial de la línea de tiempo de la historia clínica que dibuja las piezas.
- Selección de pieza y cara con detalle del estado registrado.
- Sin HTML armado en JavaScript: los datos llegan del endpoint y la vista los recorre.
- Estados por momento diferenciados visualmente —pasado, presente, futuro—.
- Estados vacíos explícitos: paciente sin registros odontológicos.
- Estilos con Bootstrap primero; CSS propio sólo para el dibujo de la pieza.

## Fuera de alcance

- Edición del estado desde el odontograma.
- Animaciones o comparación entre encuentros.

## Criterio de cierre

La historia clínica muestra el odontograma del paciente con los datos registrados, en
tablet y escritorio, sin errores de consola y sin lógica clínica en la vista.

## Depende de

[Fase 03](03-api-odontograma.md).
