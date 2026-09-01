# Plan — Asistente: catalogación unificada (1ª IA + funcionalidades)

| Campo | Valor |
|-------|--------|
| Slug | `asistente-catalogacion-unificada` |
| Estado | **Abierto** — diseño acordado en conversación; implementación pendiente |
| Reemplaza / evoluciona | Canal `guide`, preprocess `user_goal`, 2ª IA guide, regex CTA en `ChatChannelPolicy` |

## Índice

| Doc | Contenido |
|-----|-----------|
| [overview.md](./overview.md) | Alcance, entregables, fuera de alcance |
| [design.md](./design.md) | Catalogación, JSON, envelope, hidratación PHP |
| [phases/00-adr-y-schema.md](./phases/00-adr-y-schema.md) | ADR + contrato JSON 1ª IA |
| [phases/01-summaries-classifier.md](./phases/01-summaries-classifier.md) | Summaries compactos + `{funcionalidades}` |
| [phases/02-preprocess-y-router.md](./phases/02-preprocess-y-router.md) | Prompt + `ChatPreprocessService` + router |
| [phases/03-orquestacion-clara.md](./phases/03-orquestacion-clara.md) | `clara` → flow / interactive |
| [phases/04-motor-incompletas.md](./phases/04-motor-incompletas.md) | 2ª IA + `scoped_system_records` |
| [phases/05-deprecacion-guide.md](./phases/05-deprecacion-guide.md) | Retirar guide, CTA regex, hilos legacy |
| [phases/06-qa-y-cierre.md](./phases/06-qa-y-cierre.md) | Tests, QA, volcar a `producto/` + `decisions/` |

## Código tocado (referencia)

| Área | Ubicación actual |
|------|------------------|
| Preprocess | `ChatPreprocessService`, `prompts/preprocess.yaml` |
| Guide (a retirar) | `GuideChannel`, `GuidePromptAssembler`, `prompts/guide.yaml` |
| Router | `ChatRouter`, `ChatOrchestrator` |
| Catálogo | `UiActionCatalog`, `UiActionCatalogItem::toAiCandidateArray()` |
| HIS loaders | `AssistantContextAssemblyService`, aspect loaders |
| Envelope | `AssistantEnvelope` (`message` / `interactive` / `flow`) |
| Intents | `common/metadata/bioenlace/assistant/intents/**` (`intent_semantics`) |

## Documentación estable al cerrar

- [producto/asistente-y-chat.md](../../producto/asistente-y-chat.md)
- Nuevo ADR: `decisions/asistente-catalogacion-unificada.md`
- Actualizar [arquitectura/asistente-motores.md](../../arquitectura/asistente-motores.md) si cambia el diagrama de motores
