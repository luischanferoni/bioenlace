# Fase 06 — Oftalmología: campos de práctica y receta

**Estado:** cerrada.

## Objetivo

Que la IA devuelva todo lo que oftalmología ya sabe guardar. Dos de sus tres categorías
no pedían ningún campo.

## El problema encontrado

El servicio leía las claves en minúscula (`ojo`, `prueba`, `od_esfera`) mientras el
prompt no pedía nada; y aunque se hubiera pedido con mayúscula inicial, el servicio no
las habría encontrado. La receta se habría guardado vacía.

## Entregado

- [x] Contrato de práctica con código, ojo, prueba, estado, informe y tipo. El ojo se
      normaliza a OD, OI o AO; un valor no reconocido se descarta para que el
      profesional lo elija en la revisión.
- [x] Contrato de receta con las ocho medidas —esfera, cilindro, eje y adición por ojo—
      tolerante a las claves con espacio y a las de snake_case.
- [x] Modelos de entrada y completitud para ambas categorías.
- [x] La completitud de práctica exige código o prueba; la de receta exige al menos un
      valor.
- [x] Los dos contratos se registran en el registry compuesto.
- [x] El catálogo arma las secciones desde los contratos y marca todos los campos como
      extraídos.
- [x] La persistencia resuelve los campos por contrato y guarda la receta con la misma
      forma de siempre, así ningún consumidor cambia.
- [x] Un código no numérico se guarda como término local, no como SNOMED.

## Verificación

Con un encuentro real y rollback: tres prácticas producen dos observaciones —la fila
sin código ni prueba se descarta— y la receta se persiste con las medidas de ambos ojos.
Los tests cubren mapeo, ojo inválido, claves legacy y completitud por contrato.

## Fuera de alcance

- Cambiar la forma de `lens_spec_json`.

## Siguiente

La interfaz: [fase 07](07-oftalmologia-receta.md).
