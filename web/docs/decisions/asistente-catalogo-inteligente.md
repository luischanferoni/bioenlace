# Asistente: catálogo inteligente + orquestación IA

## Contexto

El chat del HIS mezcla preprocess (`user_goal`), canal **guide** (2ª IA con volcado amplio), `IntentClassifier` por keywords y regex en `ChatChannelPolicy`. Eso duplica interpretación, gasta tokens y no hay un catálogo unificado que relacione lenguaje natural con intents, artículos editoriales, aspect loaders y métricas DataAccess.

En conversación de diseño se acordó: la IA **etiqueta** (mensaje + historial); PHP **matchea, planifica y ejecuta** contra tools cerrados; síntesis IA solo cuando hace falta; planificadora IA opcional como red de seguridad; **log de planificación** para mantener el catálogo.

Documentación estable: [producto/asistente-y-chat.md](../producto/asistente-y-chat.md), [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md).

## Decisión

### Capas

1. **1ª IA (etiquetado)** — JSON v1: `normalized_text`, `necesidad_usuario`, `routing_hint`, `tags`, `context_areas`, `extractions`, `intent_ids_hint` opcional. Sin catálogo completo de funcionalidades en el prompt.
2. **Catálogo inteligente (PHP)** — metadata `assistant/catalog/smart-catalog.yaml`: entradas con `tool_id`, `tool_type`, `triggers`, anclas requeridas, template opcional. Match con score; RBAC antes de exponer tools tipo `intent` o `metric`.
3. **Routing PHP** — resultados: `directo`, `clara`, `dudosa`, `incompletas`, `fuera_de_his`. PHP decide camino final; `routing_hint` de la IA orienta pero no obliga.
4. **Match 100%** — score + margen + anclas resueltas + dato presente cuando la pregunta lo exige → respuesta templated + botones (**1 IA**).
5. **Plan declarativo** — reutiliza `AssistantContextAnchorResolver` y `AssistantContextAreaAspectResolver` (evolucionar a registry YAML); output `tool_ids` + `needs_planner`.
6. **2ª IA síntesis** — `necesidad_usuario` + `scoped_system_records`; reemplaza guide para incompletas.
7. **3ª IA planificadora (opcional)** — solo si `needs_planner` (plan vacío, demasiados tools, sin datos útiles post-load); elige `tool_ids_ordered` del **shortlist filtrado**; PHP resuelve params.
8. **Log de planificación** — estructura `planning_applied` por mensaje (ver schema metadata).

### Convención `tool_id`

| `tool_type` | Formato | Ejecutor |
|-------------|---------|----------|
| `intent` | `intent:<intent_id>` | IntentEngine / SubIntentEngine |
| `article` | `article:<topic>` | InfoContentAssistantService |
| `aspect` | `aspect:<aspect_key>` | AssistantContextAspectLoaderRegistry |
| `metric` | `metric:<metric_id>` | DataAccess vía intent read |

### Equivalencia routing (transición)

| Antes | Después |
|-------|---------|
| `user_goal: operational` | `clara` (match intent) |
| `user_goal: guide` | `incompletas` o `directo` (artículo / template) |
| `user_goal: ambiguous` | `dudosa` |
| `user_goal: in_flow_question` | `clara` o `incompletas` según match |
| `catalogacion: funcionalidades_incompletas` (plan previo) | `incompletas` |
| `catalogacion: fuera_de_his` | `fuera_de_his` |

Alias temporal de preprocess: mapear `user_goal` legacy a `routing_hint` hasta retirar fase 07.

## Alternativas descartadas

- **Catálogo completo en 1ª IA** — costo de tokens; duplica RBAC en prompt.
- **IA elige métodos PHP libres** — riesgo permisos e integridad; no testeable.
- **Planificadora siempre activa** — latencia y costo; el catálogo declarativo debe absorber casos frecuentes vía log `gaps`.
- **Solo regex/IntentClassifier sin catálogo** — no escala a artículos, aspectos y métricas en un solo match.

## Consecuencias

- Contratos JSON: `common/metadata/bioenlace/assistant/schemas/*.yaml`.
- Nuevos servicios: `SmartCatalogRegistry`, `SmartCatalogMatchService`, `DeclarativePlanService`, `AssistantPlanningLogService` (nombres en Platform/Assistant/Catalog/ y Planning/).
- Params Yii: `asistente_plan_max_tools`, `asistente_planning_debug`, umbrales de match (TBD en fase 01).
- Deprecación progresiva: canal `GuideChannel` en raíz, `user_goal` como eje, regex CTA donde el catálogo cubra el caso.
- Telemetría IA: mantener `asistente-preprocess`; añadir `asistente-planner`, `asistente-synthesis` al cablear fases 05–06.
- Documentación al cierre: actualizar `producto/asistente-y-chat.md`, `arquitectura/asistente-motores.md`.

Schemas: `assistant/schemas/first-ia-v1.yaml`, `smart-catalog-entry-v1.yaml`, `planning-log-v1.yaml`, `planner-ia-v1.yaml`.

Relacionado: [asistente-contexto-his-areas-aspectos.md](./asistente-contexto-his-areas-aspectos.md), [asistente-canal-guide.md](./asistente-canal-guide.md) (guide queda obsoleto en raíz al cerrar el plan).
