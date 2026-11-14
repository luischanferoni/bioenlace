# Fase 11 — Salud mental y psicología: sesiones y escalas

**Estado:** cerrada.

## Objetivo

Que la sesión de salud mental registre su evolución y los puntajes de screening, con su
interpretación, y que la historia clínica los muestre.

## Decisión de diseño: generalizar el panel

Obstetricia ya había estrenado una extensión de observación con `panel_id` y campo. En
lugar de crear una segunda tabla casi idéntica, la extensión pasó a ser genérica:
`observation_panel_ext` con `panel_kind`. El almacenamiento compartido de paneles vive en
`Shared/Infrastructure/Persistence` y lo usan las dos especialidades.

## Decisión de diseño: la severidad se deriva

El puntaje de una escala se guarda; la interpretación **no**. Los tramos viven en un
catálogo de instrumentos y la severidad se calcula desde el puntaje en cada lectura. Si
mañana cambia un corte, no hay datos históricos que corregir.

Los instrumentos también son fuente única: el prompt pide un campo por escala a partir
del catálogo, así agregar una escala nueva no obliga a tocar el contrato ni la sección.

## Entregado

- [x] Catálogo de instrumentos con rango, unidad y tramos de severidad: PHQ-9 y GAD-7.
- [x] Contrato de sesión: motivo, estado mental, riesgo suicida, objetivos, evolución,
      adherencia, sueño y consumo de sustancias, más un campo por instrumento.
- [x] Los puntajes se validan como numéricos y dentro del rango; un valor no numérico se
      descarta en lugar de guardarse.
- [x] Persistencia como observaciones agrupadas por sesión, compartida con obstetricia.
- [x] Lectura de sesiones con severidad por escala y serie de un instrumento para graficar.
- [x] Salud mental pasa a especialidad con captura propia; psicología la declara como
      categoría adicional, sin reclamarla.
- [x] Plantilla de workflow para salud mental y psicología.
- [x] Panel en la historia web con la escala, su puntaje y su badge de severidad.
- [x] Bloque `salud_mental` en la API de historia clínica.

## Verificación

- Tests de los tramos de severidad en los bordes de cada banda, y rechazo de puntajes
  fuera de rango o no numéricos.
- Test de que toda clave del prompt tiene forma de guardado declarada.
- Ida y vuelta contra la base con rollback: cuatro campos producen cuatro observaciones,
  los dos puntajes vuelven con su severidad correcta —PHQ-9 de 12 es moderada, GAD-7 de 8
  es leve— y la serie del instrumento devuelve el valor.
- Render del partial: escalas, badges por severidad y notas sin duplicar el puntaje.
- Migración aplicada, revertida y reaplicada tras la generalización.

## Fuera de alcance

- Los ítems individuales de cada escala: se registra el puntaje total, no el detalle.
- Cálculo del puntaje desde las respuestas del paciente.
- Interfaz en la app móvil.

## Resultado

Salud mental y psicología registran sesiones con escalas interpretadas. El límite es que
el puntaje se dicta; derivarlo de un cuestionario es otro flujo.
