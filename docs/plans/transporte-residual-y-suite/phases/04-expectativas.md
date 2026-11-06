# Fase 04 — Expectativas de producto

## Objetivo

Resolver las fallas que **no** son de armado: el test afirma algo sobre prompts, catálogos o
flows y el producto cambió (o nunca coincidió). Acá no se "arregla el test" en masa: cada
familia exige decidir qué es lo correcto.

## Tamaño estimado

**Mediano (1–2 días).** Es la fase con más decisiones y la que puede destapar deuda real.

## Antes de empezar

Volver a medir. Los conteos de abajo son del 2026-11-06 y cambian con cualquier ajuste de
prompts o flows. Agrupar las fallas por clase de test y atacar familia por familia: no por
archivo suelto.

## Familias

| Familia | Fallas | Qué se decide |
|---------|--------|---------------|
| DataAccess y catálogos declarativos | 7 | Si la métrica/entidad nueva debe estar descubierta o el test quedó viejo |
| Prompts y ensamblado del asistente (guide, preprocess, semantics, state tags) | 14 | Si el texto del prompt cambió a propósito; el test debe fijar el contrato, no la redacción |
| Catálogos QA del asistente y routing conversacional | 6 | Si la pregunta de QA sigue siendo válida para el catálogo actual |
| Flows YAML y normalizadores clínicos | 12 | Si el flow cambió de pasos o el test asume un orden viejo |
| Seeds y catálogos geográficos / de precios | 3 | Si el seed cambió de fuente (BD vs. YAML) |
| Resto disperso | 15 | Caso por caso |

## Reglas de la casa que aplican

- Un test de prompts fija **estructura y placeholders**, no redacción. Si el cambio de texto
  es intencional, la aserción se ajusta; si es accidental, se revierte el texto.
- Un test de catálogo valida contra la **fuente única** (YAML o BD), no contra una copia.
- Si la falla destapa un mensaje literal que debería salir del catálogo de la API, se anota en
  [mensajes-respuesta](../../mensajes-respuesta/README.md) y no se resuelve acá.

## Tareas

- [ ] Agrupar las fallas por familia y anotar, para cada una, cuál de las dos cosas pasó:
      cambió el producto, o el test nunca reflejó el producto.
- [ ] Resolver primero las que son "el test quedó viejo" (bajo riesgo y bajan el ruido).
- [ ] Las que implican cambiar producto: llevarlas al chat con la decisión explícita antes de
      tocar el código.
- [ ] Documentar las fallas que se deciden **no** arreglar (con motivo) para que el suite no
      quede rojo sin explicación: usar `markTestSkipped` con el motivo, nunca borrar el test.

## Aceptación

- `codecept run unit` en verde, con los `skipped` explicados.
- Ninguna aserción relajada sin motivo escrito en el propio test o en el commit.
- Las fallas que se resolvieron cambiando producto aparecen en el changelog de la fase.

## Riesgos

- **Falsa confianza.** Un test que se ajusta a la salida actual deja de detectar regresiones.
  Antes de tocar la aserción, comprobar que la salida nueva es la correcta.
- **Prompts.** Ver la regla de la casa: los prompts del asistente no se modifican sin
  revisión; si el test falla por el prompt, primero se decide el prompt.
