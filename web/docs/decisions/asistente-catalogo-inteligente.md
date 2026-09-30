# Asistente: catálogo inteligente + orquestación IA

> **Obsoleto.** Reemplazado por [asistente-discovery-unificado.md](./asistente-discovery-unificado.md). La 3ª IA planificadora de este diseño fue retirada.

## Contexto

El chat del HIS mezcla preprocess (`user_goal`), canal **guide** (2ª IA con volcado amplio), `IntentClassifier` por keywords y regex en `ChatChannelPolicy`. Eso duplica interpretación, gasta tokens y no hay un catálogo unificado que relacione lenguaje natural con intents, artículos editoriales, aspect loaders y métricas DataAccess.

En conversación de diseño se acordó: la IA **etiqueta** (mensaje + historial); PHP **matchea, planifica y ejecuta** contra tools cerrados; 2ª IA guide cuando hace falta; **log de planificación** para mantener el catálogo.

Documentación estable: [producto/asistente-y-chat.md](../producto/asistente-y-chat.md), [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md).

## Decisión

### Capas

1. **1ª IA (etiquetado)** — JSON (`first-ia-v1`, v2): `normalized_text`, `necesidad_usuario` / `necesidades_usuario`, `routing_hint`, `tags`, `extractions`; `intent_ids_hint` opcional y deprecado. Sin catálogo completo de funcionalidades en el prompt. **No** pide `context_areas`: si vienen, PHP las ignora.
2. **Áreas de contexto (PHP)** — se derivan del match del smart-catalog → `tool_ref` / CTA → carpeta de dominio del intent (`IntentSchemaPaths::domainForIntentId` / `AssistantContextAreaDerivation`). El área es la carpeta bajo `metadata/bioenlace/<dominio>/intents/`, no un campo de la 1ª IA. Ver [arbol-espejo-dominios.md](../arquitectura/arbol-espejo-dominios.md).
3. **Catálogo inteligente (PHP)** — metadata `platform/assistant/catalog/smart-catalog.yaml`: entradas con `tool_id`, `tool_type`, `triggers`, anclas requeridas, template opcional. Match con score; RBAC antes de exponer tools tipo `intent` o `metric`.
4. **Routing PHP** — resultados: `clara` (match 100 %: intent, artículo o template), `dudosa`, `incompletas`, `fuera_de_his`. PHP decide camino final; `routing_hint` de la IA orienta pero no obliga.
5. **Match 100%** — score + margen + tool ejecutable → flow, artículo o template (**1 IA**). Empate → **incompletas** si hay tema HIS; no botones entre intents.
6. **Plan declarativo** — reutiliza `AssistantContextAnchorResolver` y `AssistantContextAreaAspectResolver` (evolucionar a registry YAML); output `tool_ids`.
7. **2ª IA guide** — incompletas y charla: `scoped_system_records` + prompt `channels/Guide`; líneas de ámbito desde áreas **derivadas**; CTAs de catálogo vía `CatalogCtaResolver`.
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
| `user_goal: guide` | `incompletas` (pregunta HIS) o `clara` (artículo / template / flow al 100 %) |
| `user_goal: ambiguous` | `dudosa` |
| `user_goal: in_flow_question` | `clara` o `incompletas` según match |
| `catalogacion: funcionalidades_incompletas` (plan previo) | `incompletas` |
| `catalogacion: fuera_de_his` | `fuera_de_his` |

Alias temporal de preprocess: mapear `user_goal` de hilo ↔ `routing_hint` en `PreprocessRoutingHintCatalog` (constantes PHP; el YAML de hints solo lleva id → texto para el prompt). Sin alias `directo` ni otros remapeos de hint.

## Alternativas descartadas

- **Catálogo completo en 1ª IA** — costo de tokens; duplica RBAC en prompt.
- **IA elige métodos PHP libres** — riesgo permisos e integridad; no testeable.
- **Solo regex/IntentClassifier sin catálogo** — no escala a artículos, aspectos y métricas en un solo match.
- **`context_areas` / `his_areas` pedidos a la 1ª IA** — inventaba o reconciliaba áreas; la carpeta del intent ya es el área. Pedirlas a la IA duplicaba verdad y forzaba reconcile (p. ej. síntoma → `clinical_record`).

## Consecuencias

- Contratos JSON: `common/metadata/bioenlace/platform/assistant/schemas/*.yaml`.
- Nuevos servicios: `SmartCatalogRegistry`, `SmartCatalogMatchService`, `DeclarativePlanService`, `AssistantPlanningLogService` (nombres en Platform/Assistant/Catalog/ y Planning/).
- Params Yii: `asistente_planning_debug`, umbrales de match.
- Deprecación progresiva: canal `GuideChannel` en raíz, `user_goal` como eje, regex CTA donde el catálogo cubra el caso; campo `context_areas` en preprocess (ignorado; quitar del prompt a mano).
- Telemetría IA: mantener `asistente-preprocess`; incompletas usan `asistente-guide`.
- Documentación: [producto/asistente-y-chat.md](../producto/asistente-y-chat.md), [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md), [arquitectura/arbol-espejo-dominios.md](../arquitectura/arbol-espejo-dominios.md).

Schemas: `platform/assistant/schemas/first-ia-v1.yaml`, `smart-catalog-entry-v1.yaml`, `planning-log-v1.yaml`.

Relacionado: [asistente-contexto-his-areas-aspectos.md](./asistente-contexto-his-areas-aspectos.md), [asistente-canal-guide.md](./asistente-canal-guide.md) (guide queda obsoleto en raíz al cerrar el plan).
