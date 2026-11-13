# Fase 03 — API del odontograma

**Estado:** pendiente.

## Objetivo

Que cualquier cliente —web o móvil— obtenga el estado del odontograma de un paciente
sin conocer la estructura de tablas.

## Alcance

- Endpoint de solo lectura bajo el encounter: estado acumulado de las piezas hasta ese
  encuentro, más el detalle del encuentro actual.
- Respuesta por pieza: número, caras afectadas, estado, momento y el encuentro donde se
  registró. Las piezas sin registro no se inventan: el cliente dibuja el diente sano.
- Permisos por la misma vía que el resto de las lecturas clínicas; sin reglas nuevas en
  el controlador.
- Sin datos de otros pacientes ni de encuentros posteriores al solicitado.

## Fuera de alcance

- Escritura desde el odontograma. La captura sigue siendo el punto de entrada.
- Agregaciones por efector o por profesional.

## Criterio de cierre

Un cliente autenticado obtiene el odontograma de un paciente en una sola llamada y la
respuesta coincide con lo registrado en las observaciones de estado.

## Depende de

[Fase 02](02-odontologia-observacion.md).
