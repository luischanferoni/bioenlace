# Hints y presentación en flows

## Objetivo

Cómo declarar y resolver `hint` en YAML y exponerlos en `flow` / `open_ui`.

## Actores

- Autor de sub-intents; cliente que muestra sugerencias en pasos.

## Anclas

| Área | `open_ui_hints` en manifest, resolución en SubIntentEngine |

---

## YAML (por subintent)

```yaml
hint:
  entity: servicio
  match_property: nombre
```

- `entity` enlaza con `extraction.category` del preprocess.
- `draft_field` se infiere de `provides` (`draft.id_servicio_asignado` → `id_servicio_asignado`).

## Resolución (servidor)

1. Preprocess → `extractions[]` con `span`, `category`, `synonyms`.
2. Tras elegir intent → `FlowHintService::resolveForIntent()` lee `hint.entity` del YAML y llama a `HintResolutionService` → `HintCandidateProvider::forEntity(entity, draft, intentId)` + `HintEntityMatcher` (fuzzy).
3. Los candidatos **no** dependen del `action_id` del `open_ui`: el proveedor usa la entidad y el draft (p. ej. efectores del `id_servicio_asignado` ya elegido).
4. Resultado: `hints[]` con `{ entity, id, value, draft_field }`.

## Sobre `flow` (cliente)

```json
{
  "kind": "flow",
  "hints": [
    { "entity": "servicio", "id": "12", "value": "Obstetricia", "draft_field": "id_servicio_asignado" }
  ]
}
```

El cliente agrupa por `entity` y decide append/preselect/query (list vs search).

## Query en `open_ui`

`SubIntentEngine` mergea en `client_open.api.query` el `draft_field` → `hint.id` y, si aplica, `q=hint.value`.
