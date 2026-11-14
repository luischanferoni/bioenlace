# Fase 10 — Obstetricia: control prenatal

**Estado:** cerrada.

## Objetivo

Que el control prenatal registre los parámetros del embarazo y que la historia clínica
los muestre como un panel.

## Diseño de datos

Es la primera especialidad sin persistencia previa. Se resolvió con el modelo clínico
existente más una extensión chica:

| Pieza | Para qué |
|-------|----------|
| `observation` (categoría `obstetrics`) | Una fila por parámetro |
| `observation_obstetrics_ext` | Agrupa por control (`panel_id`) y guarda el campo del panel |

Una observación por parámetro, no un JSON único, para poder graficar la evolución —por
ejemplo, altura uterina a lo largo del embarazo— sin parsear nada.

Los códigos salen del contrato. Los que tienen equivalente SNOMED verificable lo usan
(FUM, edad gestacional); el resto declara el sistema local `bioenlace-obstetrics` en
lugar de inventar un código.

## Entregado

- [x] Contrato del control prenatal: once campos con su código, sistema, unidad y tipo
      de valor, declarados en un solo lugar.
- [x] Alias en español y abreviaturas clínicas (`EG`, `AU`, `FCF`), descartando las
      letras sueltas ambiguas (`g`, `p`, `a`) para no confundirlas con otras categorías.
- [x] Modelo de captura, entrada tipada con validación numérica y completitud.
- [x] Persistencia: un control agrupa sus observaciones y devuelve cuántas escribió.
- [x] Lectura del panel por paciente, del control más reciente al más antiguo, y serie
      de un parámetro para graficar.
- [x] Migración de la extensión, idempotente y reversible.
- [x] Catálogo: obstetricia pasa a especialidad con captura propia y plantilla de
      workflow que incluye el control prenatal y los signos vitales.
- [x] Sección en la historia web y bloque `control_prenatal` en la API de historia.

## Verificación

- Test de ida y vuelta contra la base, con rollback: cinco parámetros producen cinco
  observaciones, el panel se reconstruye en el orden del contrato, las unidades se
  conservan y la serie devuelve el valor numérico.
- Migración aplicada, revertida y reaplicada.
- Se renderizó el partial y se comprobó título, fecha, valores y unidades.

## Fuera de alcance

- Cálculo automático de la edad gestacional desde la FUM y la fecha del control.
- Curvas de crecimiento fetal o percentilos obstétricos.
- Interfaz en la app móvil.

## Resultado

Obstetricia registra controles prenatales consultables por parámetro. El límite es que
la edad gestacional se dicta, no se deriva; derivarla requiere fecha de referencia y es
una decisión de dominio aparte.
