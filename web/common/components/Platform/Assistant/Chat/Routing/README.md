# Routing

| `user_goal` | Canal |
|-------------|-------|
| `guide` | `GuideChannel` |
| `operational` / `in_flow_question` | `OperationalChannel` |
| `ambiguous` | `AmbiguousChannel` |

`ChatRouter::refineGoalForHisContext` puede reorientar a `guide` cuando hay `context_areas` y la pregunta no es trámite operativo.
