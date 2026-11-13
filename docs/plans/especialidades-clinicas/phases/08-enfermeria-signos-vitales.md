# Fase 08 — Enfermería: signos vitales completos

**Estado:** cerrada.

## Objetivo

Que la IA devuelva los signos vitales que la enfermería ya sabe guardar y mostrar. Seis
de los diez estaban comentados en el modelo.

## El problema encontrado

La normalización y la lectura ya soportaban los diez signos, y el bloque de episodio ya
publicaba la serie completa. Sólo faltaba pedirlos: frecuencia respiratoria, saturación
de oxígeno, glucemia, Glasgow, peso y talla.

Además, la completitud caía en el camino legacy —lista plana de campos—, así que una
fila con un solo signo se marcaba incompleta y exigía los cuatro declarados.

## Entregado

- [x] Contrato de fila para signos vitales con las diez claves del prompt.
- [x] La normalización de alias y la conversión de decimales viven en el contrato: una
      sola fuente para las claves que se persisten.
- [x] El caso de uso de documentación delega en el contrato en lugar de mantener su
      propia copia del mapa.
- [x] El modelo de enfermería toma los campos del prompt del contrato, sin lista propia.
- [x] Completitud propia: un signo vital alcanza; la fila sólo se marca incompleta si no
      hay ninguno, y en ese caso lista los diez campos posibles.
- [x] La etiqueta de la fila resume la tensión cuando está presente.

## Verificación

- Un test recorre cada campo del prompt y comprueba que la normalización lo conserva: si
  alguien agrega un campo que nadie persiste, falla.
- Tests de alias, tildes, mayúsculas, coma decimal, claves desconocidas y fusión de
  filas.
- Se generó el bloque de prompt real y se comprobó que pide los diez campos, en el
  listado y en el ejemplo estructurado.

## Fuera de alcance

- El formulario de enfermería fuera de la captura.
- Validar rangos nuevos: los umbrales existentes se mantienen.

## Resultado

Enfermería pasa de cuatro a diez parámetros interpretados sin migración ni cambio de
tabla, porque la columna de datos ya los admitía.
