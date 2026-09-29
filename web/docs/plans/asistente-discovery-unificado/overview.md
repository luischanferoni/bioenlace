# Overview — Discovery unificado

## Objetivo

Que el asistente deje de tener un catálogo paralelo de “puertas” (`direct-doors` / smart-catalog) y un routing PHP con `clara` / `incompletas` / `dudosa` que reinterpreta lo que ya dijo el preprocess.

Flujo norte:

```text
preprocess → hint ∈ { guide | fuera_his | sin_pedido }
                ↓
         si guide:
           tags (+ texto) → índice único de discovery
             • intents: meta.tags en YAML Domain (ricos)
             • editorial: tags/keywords en BD (artículos), misma idea
                ↓
           hits → adjuntos a Guide (intent_semantics, article_block, …)
                ↓
           2ª IA siempre (nunca DirectMatch)
```

## Por qué

Hoy conviven: hints IA (`pedido_claro`…), paths PHP (`clara`…), `direct-doors` para aspectos/artículos/fuera-HIS, y match de tags de estados. Misma idea de producto (descubrir por tags) en tres formatos. Los aspectos-puerta y el match 100 % a artículo sin Guide son deuda respecto a “todo se orienta en la 2ª IA”.

## Actores

- Preprocess (1ª IA): lectura del mensaje → hint + tags.
- Discovery PHP: cruza tags con intents/artículos; carga adjuntos.
- Guide (2ª IA): responde con adjuntos; no inventa trámites.
- Quien edita intents Domain y artículos en admin.

## Fuera de este plan

- Reescribir el cuerpo de prompts (`Channels/*/prompt.yaml`, `Preprocess/prompt.yaml`): los edita una persona; el plan solo sugiere textos.
- Cambiar la semántica clínica de Solicitar Atención / flows.
- Meter el cuerpo de artículos en YAML Domain (siguen en BD).

## Fases

| Fase | Qué |
|------|-----|
| [01](./phases/01-hints-y-guide-only.md) | Hints `guide` / `fuera_his` / `sin_pedido`; legacy fuera; artículos no saltean Guide |
| [02](./phases/02-indice-discovery.md) | Índice único tags → hits (intents + artículos); adjuntos a Guide |
| [03](./phases/03-retirar-puertas.md) | Retirar `direct-doors`, aspectos-como-puerta; migrar “llego tarde” a intent rico o artículo |
| [04](./phases/04-docs-y-cierre.md) | Actualizar ADR/producto/QA; borrar plan al cerrar |

## Documentación estable al cerrar

- `producto/asistente-y-chat.md`
- `producto/contenido-informativo.md`
- `arquitectura/asistente-motores.md`
- Nuevo o reemplazo de `decisions/asistente-catalogo-inteligente.md`
