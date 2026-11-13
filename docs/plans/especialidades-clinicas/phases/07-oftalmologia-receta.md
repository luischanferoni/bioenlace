# Fase 07 — Receta de lentes en web y app

**Estado:** cerrada.

## Objetivo

Que la receta de lentes se vea como grilla OD/OI en la historia clínica, igual en web y
en la app.

## Entregado

- [x] Lectura de la última receta del paciente con las medidas por ojo.
- [x] Bloque `receta_lentes` en la respuesta de historia clínica que consume la app.
- [x] Sección en la historia web con filas esfera, cilindro, eje y adición, y columnas
      OD/OI. Los valores ausentes se muestran con un guion, no con cero.
- [x] View builder que arma las filas: la vista sólo recorre.
- [x] Tarjeta equivalente en la app, hermana del odontograma, con los mismos textos en
      el catálogo.
- [x] Tests del view model y del modelo de lectura: orden de filas, un solo ojo con
      valores, y respuesta sin bloque.

## Verificación

Se renderizó el partial con una receta de prueba y se comprobó título, fecha, columnas,
medidas y guion en los valores ausentes. Análisis estático de la app sin errores nuevos
y tests de historia clínica en verde.

## Fuera de alcance

- Emitir la receta como documento.
- Historial de recetas anteriores.

## Resultado

Oftalmología queda cerrada como odontología: la IA extrae las medidas, la captura las
persiste, la API las publica y ambas plataformas las muestran.
