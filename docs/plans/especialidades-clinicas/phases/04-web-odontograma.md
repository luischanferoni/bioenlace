# Fase 04 — Odontograma en la historia web

**Estado:** cerrada.

## Objetivo

Que el odontograma se vea dentro de la historia clínica del paciente en la web, como
interfaz común y no como pantalla del asistente.

## Entregado

- [x] Sección del odontograma en la línea de tiempo, visible sólo si el paciente tiene
      estados registrados.
- [x] Piezas agrupadas por dentición —permanente, temporaria, sin clasificar— en el
      orden que devuelve el servicio.
- [x] Cada pieza muestra su estado actual con el momento diferenciado por color, y
      despliega su historia con fecha, caras y momento.
- [x] Un view builder traduce los datos a filas listas para pintar: la vista sólo
      recorre y codifica.
- [x] Desplegado con el componente de colapso de Bootstrap; sin JavaScript propio para
      esta pantalla y sin HTML armado en JS.
- [x] Sólo utilidades de Bootstrap para la grilla; sin hoja de estilos nueva.
- [x] Tests del view model: agrupado, orden, clases y etiquetas por momento, prioridad
      de texto y saneamiento del identificador de destino.

## Verificación

Se renderizó el partial con un odontograma de prueba y se comprobó que emite el título,
las piezas de ambas denticiones, el identificador de destino, los badges por momento,
las caras y la fecha corta.

## Fuera de alcance

- Edición del estado desde el odontograma.
- Comparación entre encuentros.
- Dibujo anatómico de la pieza.

## Resultado

La historia web muestra el odontograma. Falta la app:
[fase 05](05-flutter-odontograma.md).

