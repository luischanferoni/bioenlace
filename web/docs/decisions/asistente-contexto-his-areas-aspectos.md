# Contexto HIS del asistente: áreas + aspectos

> **Obsoleto (áreas).** El catálogo `context-his-areas.yaml` / `AssistantContextHISArea` fue retirado. Discovery adjunta `intent_semantics` + artículos; `context_areas` queda vacío. Los aspectos/loaders siguen como legado del plan declarativo. La 3ª IA planificadora fue retirada.

## Contexto

La 2ª IA del canal **guide** necesita datos del HIS sin pegar la historia clínica completa. Antes se usaban áreas HIS → aspectos → loaders.

## Decisión (histórica)

| Nivel | Código | Estado |
|-------|--------|--------|
| **Área HIS** | ~~`AssistantContextHISArea`~~ | Retirado |
| **Aspecto** | `AssistantContextHISAreaAspect` | Legado |

Ver [asistente-discovery-unificado.md](./asistente-discovery-unificado.md).

Documentación: [producto/asistente-y-chat.md](../producto/asistente-y-chat.md), [arquitectura/asistente-motores.md](../arquitectura/asistente-motores.md).
