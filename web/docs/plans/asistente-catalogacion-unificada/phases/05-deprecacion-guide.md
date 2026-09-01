# Fase 05 — Deprecación guide y regex

## Objetivo

Eliminar caminos duplicados.

## Tareas

- [ ] `GuideChannel::handle` fuera del router raíz (o delegar 100% a incompletas).
- [ ] Eliminar / reducir `ChatChannelPolicy::isClinicalSymptomContent` en camino chat (mantener solo si metadata DataAccess lo referencia).
- [ ] `AssistantThreadStateService`: adaptar a `catalogacion` o simplificar si CTA viene de `intent_ids`.
- [ ] `user_goal` en preprocess.yaml y `GOALS` en PHP: retirar o alias temporal documentado en ADR.
- [ ] `AmbiguousChannel` para `dudosa`; copy en metadata.
- [ ] Handler `fuera_de_his` con límites de producto.
- [ ] Limpiar tests que asumen guide channel / `user_goal: guide`.

## Criterio de salida

No hay llamada a `asistente-guide` en camino raíz salvo síntesis incompletas; grep sin `GuideChannel::handle` desde `ChatRouter` raíz.
