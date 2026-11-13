# Fase 05 — Odontograma en la app Personal de Salud

**Estado:** cerrada.

## Objetivo

Que el profesional vea el mismo odontograma en la app, con la misma información que en
la web.

## Decisión de diseño

El odontograma se agrega a la respuesta de historia clínica que la app ya consume, en
lugar de una llamada extra por encuentro. La app no necesita conocer el identificador
del encuentro ni repetir el cálculo, y la web y la app leen el mismo servicio.

## Entregado

- [x] Bloque `odontograma` en la respuesta de historia clínica.
- [x] Modelo de lectura con piezas, estados, caras, momento y totales.
- [x] Tarjeta en la línea de tiempo, hermana de signos vitales, sólo visible cuando el
      paciente tiene piezas con registro.
- [x] Piezas agrupadas por dentición, con el estado actual y su historial desplegable.
- [x] Momento del estado diferenciado con la etiqueta de estado del sistema de diseño.
- [x] Todos los textos visibles en el catálogo de textos de la app, sin literales en
      pantalla.
- [x] Tests del modelo de lectura: piezas, estado actual, historial vacío y respuesta
      sin bloque.

## Verificación

Análisis estático sin errores nuevos en los archivos tocados y tests de historia clínica
en verde, incluidos los dos casos del odontograma.

## Fuera de alcance

- Edición del odontograma en la app.
- Dibujo anatómico de la pieza.

## Resultado

La especialidad queda cerrada de punta a punta: la IA extrae pieza, caras y momento; la
captura los persiste en observaciones; la API agrupa el estado por pieza; la historia web
y la app lo muestran.

