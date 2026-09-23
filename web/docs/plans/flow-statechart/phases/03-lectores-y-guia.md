# Fase 3 — Lectores directos y guía

## Objetivo

`SubIntentEngine`, `FlowManifest`, `FlowHintService`, catálogo e índice de permisos leen `states`. Se elimina `StatechartManifest` y el contrato deja de documentar `subintents` / `next_routing` / `draft_equals`.

## Guía

`IntentSemanticsPromptFormatter` arma el bloque de la 2ª IA desde el statechart: objetivo, estado `initial` y sus `always`, más los estados `type: final` (los sumideros). En un grafo grande no vuelca los treinta estados. Se retira `outline` de los intents migrados.

El texto del prompt de canal lo pega una persona si hace falta una frase que nombre el formato. El agente no edita ese YAML.

## Checklist

- [ ] Lectores usan `states` sin compilar a `subintents`
- [ ] Borrar `StatechartManifest`
- [ ] Formatter de la guía
- [ ] Contrato sin el vocabulario viejo
- [ ] Tests de regresión de Solicitar Atención y de un flow lineal
