# Asistente

Índice del **asistente conversacional** (chat, flows, UI JSON, RBAC de acciones).

| Documento | Contenido |
|-----------|-----------|
| [overview.md](./overview.md) | Qué es, objetivo, actores |
| [design.md](./design.md) | Por qué YAML + manifest + UI JSON |

## Contratos y guías (`flows/`)

| Tema | Archivo |
|------|---------|
| Sobre del chat (envelope) | [flows/ASSISTANT_ENVELOPE_CONTRACT.md](./flows/ASSISTANT_ENVELOPE_CONTRACT.md) |
| Intents YAML | [flows/YAML_INTENTS_CONTRACT.md](./flows/YAML_INTENTS_CONTRACT.md) |
| UI JSON embebible | [flows/UI_JSON_DESCRIPTOR_CONTRACT.md](./flows/UI_JSON_DESCRIPTOR_CONTRACT.md) |
| Manifiesto de flow | [flows/FLOW_MANIFEST_Y_DEPLOY.md](./flows/FLOW_MANIFEST_Y_DEPLOY.md) |
| Hints y presentación | [flows/HINTS_AND_PRESENTATION.md](./flows/HINTS_AND_PRESENTATION.md) |
| Acciones comunes / atajos | [flows/COMMON_ACTIONS_ATOJOS.md](./flows/COMMON_ACTIONS_ATOJOS.md) |
| Views JSON vs nativas | [flows/VIEWS_JSON_VS_NATIVAS.md](./flows/VIEWS_JSON_VS_NATIVAS.md) |
| Custom widgets | [flows/VIEWS_JSON_CUSTOM_WIDGET.md](./flows/VIEWS_JSON_CUSTOM_WIDGET.md) |
| Views embebibles v1 | [flows/VIEWS_EMBEBIBLES_CONTRACT_V1.md](./flows/VIEWS_EMBEBIBLES_CONTRACT_V1.md) |
| Roadmap | [flows/ASSISTANT_ROADMAP.md](./flows/ASSISTANT_ROADMAP.md) |
| Usabilidad | [flows/usabilidad.md](./flows/usabilidad.md) |

## Anclas en código

| Rol | Ubicación |
|-----|-----------|
| Motor de subintents | `common/components/Assistant/SubIntentEngine/` |
| Schemas YAML | `SubIntentEngine/schemas/intents/*.yaml` |
| Manifiesto | `Assistant/FlowManifest/FlowManifest.php` |
| UI pantallas | `common/components/Ui/UiScreenService.php` |
| Catálogo acciones | `Assistant/IntentEngine/UiActionCatalog.php` |
