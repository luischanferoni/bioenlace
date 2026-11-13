# Fase 03 — API del odontograma

**Estado:** cerrada.

## Objetivo

Que cualquier cliente —web o móvil— obtenga el estado del odontograma de un paciente
sin conocer la estructura de tablas.

## Decisión de diseño

El odontograma se expone en el endpoint de especialidad del encounter que ya existe,
en lugar de una ruta nueva por paciente. El encounter ya resuelve la autorización y
permite leer el estado acumulado del paciente hasta ese encuentro, que es exactamente
lo que la historia clínica muestra.

## Entregado

- [x] Clave `odontogram` en la respuesta de `GET /api/v1/clinical/encounter/<id>/odontology`.
- [x] Agrupación por pieza con historia completa y estado actual, ordenada por número
      de pieza.
- [x] Dentición derivada de la notación FDI: permanentes 11–48, temporarias 51–85.
      Una pieza fuera de rango queda sin dentición en lugar de asumirse.
- [x] Cada estado incluye código, texto, caras, momento, encuentro de origen y fecha.
- [x] Totales de piezas, estados y estados sin pieza.
- [x] Las piezas sin registro no se emiten: el cliente dibuja el diente sano.
- [x] Autorización por la vía existente del encounter; sin reglas nuevas en el
      controlador ni rutas adicionales.
- [x] Tests del agrupado, el orden, el estado actual y la clasificación de dentición.

## Verificación

Con dos encuentros reales del mismo paciente y un rollback al final: el odontograma
del primero muestra dos piezas; el del segundo acumula tres, con la pieza registrada
en ambos con dos estados y su último estado como actual, y la pieza temporaria
clasificada como tal.

## Fuera de alcance

- Escritura desde el odontograma. La captura sigue siendo el punto de entrada.
- Agregaciones por efector o por profesional.
- Comparación entre encuentros.

## Resultado

Web y app pueden dibujar el odontograma con una sola llamada. Falta la interfaz:
[fase 04](04-web-odontograma.md) y [fase 05](05-flutter-odontograma.md).

