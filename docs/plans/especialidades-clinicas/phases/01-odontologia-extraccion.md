# Fase 01 — Odontología: pieza, caras y momento

**Estado:** cerrada.

## Objetivo

Que la IA devuelva los datos que la odontología ya sabía guardar, y que esos datos
lleguen efectivamente a la base.

## El problema encontrado

El servicio de persistencia leía las claves en minúscula mientras el prompt pedía las
claves con mayúscula inicial. El resultado era que pieza, superficies y momento se
guardaban vacíos aunque el profesional los dictara. El arreglo no era del prompt: era
resolver las claves de forma insensible a mayúsculas y tildes, con un único
resolutor compartido.

## Entregado

- [x] Tres campos nuevos por fila odontológica: pieza en notación FDI, caras y momento
      del tratamiento.
- [x] La lista de claves vive en el contrato de fila y la consumen el modelo de entrada
      y el catálogo.
- [x] Los valores admitidos de momento son una constante única, usada por el contrato,
      la validación, el catálogo y el servicio.
- [x] La persistencia de prácticas, diagnósticos y estados resuelve los campos por el
      contrato, de modo que una clave capitalizada o en minúscula persiste igual.
- [x] Un momento no reconocido se descarta en lugar de inventarse: la fila queda sin
      momento para que el profesional lo elija en la revisión.
- [x] La validación sigue exigiendo tipo o código; pieza, caras y momento son
      opcionales. Una fila con sólo pieza se marca incompleta, no se completa sola.
- [x] Tests de mapeo de campos, descarte de momento inválido y completitud.

## Propuesta de prompt

Las indicaciones para la IA —notación FDI, caras admitidas y valores de momento— están
en un archivo hermano del prompt de captura, marcado como sugerencia. No se editó el
prompt activo.

## Siguiente

El estado dental todavía se lee parseando la nota de la condición. Eso lo resuelve la
[fase 02](02-odontologia-observacion.md).
