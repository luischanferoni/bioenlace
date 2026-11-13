# Fase 02 — Estado dental en observación consultable

**Estado:** cerrada.

## Objetivo

Que el estado de cada pieza dental se pueda consultar por columna —pieza, caras,
momento— sin leer y parsear el texto de una nota clínica.

## Qué cambió

- [x] Extensión 1:1 de `observation` con pieza, superficies y momento del tratamiento.
- [x] `EncounterDentalStatus` se persiste como observación de categoría `odontología`
      con el código del estado como código de la observación.
- [x] La lectura del estado acumulado del paciente y la del encounter consultan la
      observación unida a su extensión, ordenadas por la evolución del registro.
- [x] El bundle del encounter de odontología expone los estados dentales junto a
      prácticas y diagnósticos.
- [x] La condición deja de usarse para el estado: queda sólo para diagnósticos.
- [x] La tabla nueva se incluye en la purga del entorno de demostración.

## Migración de datos

Los estados que hoy viven como nota `odontology_state:` de una condición se copian a
observaciones. Cada observación migrada conserva el identificador de su condición de
origen, de modo que volver a correr la migración no duplica. Las condiciones de origen
no se tocan: siguen siendo el respaldo histórico.

El rollback borra únicamente lo que creó la migración. Como el origen sigue intacto en
la condición, una nueva ejecución lo reconstruye.

## Verificación

- Migración aplicada, revertida y reaplicada sobre la base local sin errores.
- Backfill probado en una base descartable con cuatro condiciones de muestra: migró la
  válida, ignoró la que no tenía código, ignoró la que era diagnóstico y ignoró la que
  tenía JSON inválido. Una segunda corrida no duplicó filas.
- Tests del mapeo de fila a estado dental, incluido el descarte de un momento no
  reconocido.

## Resultado

El odontograma ya tiene de dónde leer: una fila por estado, con pieza y caras en
columnas. Falta exponerlo como endpoint ([fase 03](03-api-odontograma.md)).
