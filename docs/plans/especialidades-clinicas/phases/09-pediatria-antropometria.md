# Fase 09 — Pediatría: antropometría y curvas de crecimiento

**Estado:** cerrada.

## Objetivo

Que el control pediátrico registre peso, talla, perímetro cefálico y circunferencia
abdominal, y que esos valores lleguen a las curvas de crecimiento.

## Dos problemas encontrados

**1. El perímetro cefálico nunca se pedía.** La curva de perímetro cefálico lee
`perimetro_cefalico` de la atención de enfermería, pero ni el prompt ni la normalización
lo contemplaban: la curva existía sin datos posibles. Lo mismo con la circunferencia
abdominal.

**2. La consulta de crecimiento estaba rota en MySQL 8.** Los paths JSON de las claves
legacy con SNOMED empiezan con dígito (`$.162879003p`), y MySQL exige comillas ahí
(`$."162879003p"`). Sin ellas la consulta falla entera, así que **ninguna** curva se
dibujaba. El arreglo es de una línea por ocurrencia, en el repositorio de persona.

## Entregado

- [x] Perímetro cefálico y circunferencia abdominal en el contrato de signos vitales,
      con sus alias y unidades. La antropometría queda declarada como subconjunto.
- [x] Sección pediátrica en el catálogo como categoría **adicional** al workflow
      estándar, no como reemplazo. La distinción importa: una categoría adicional no
      identifica a la especialidad, así que un encounter de enfermería no se reporta
      como pediátrico.
- [x] Plantilla de workflow pediátrico: el estándar más el paso de signos vitales y
      antropometría, elegida por nombre de la oferta.
- [x] Los campos del prompt de enfermería quedan también como constante del modelo, para
      poder leerlos sin instanciar el ActiveRecord.
- [x] Paths JSON corregidos en las dos consultas de crecimiento y signos vitales.

## Verificación

- Test de regresión contra la base: con claves nuevas y con claves legacy, la consulta
  devuelve peso, talla y perímetro cefálico, y calcula el IMC y la edad en meses.
- Test que ata el contrato al gráfico: las claves que normaliza la captura son las que
  consulta la curva.
- Se generó el bloque de prompt real y el workflow pediátrico completo.

## Nota sobre el alcance

Se corrigió la consulta porque sin ella la fase no puede cumplir su objetivo. No se
tocaron los percentilos ni las tablas de referencia.

## Fuera de alcance

- Inmunizaciones como categoría propia.
- Hitos del desarrollo.
- Edición de las tablas de percentilos.

## Resultado

Pediatría ya registra y grafica crecimiento. El límite de edad de las curvas sigue
siendo el que ya tenía el perfil del paciente.
