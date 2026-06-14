# Metadata del producto Bioenlace

Metadata declarativa específica del rubro (salud). Los **motores genéricos** la consumen vía `common\components\Platform\Core\Product\ProductMetadataPaths`.

Para desplegar otro vertical: copiar esta carpeta, ajustar YAML y opcionalmente fijar `productMetadataDir` en `common/config/params-local.php`.

## Estructura

| Ruta | Contenido |
|------|-----------|
| `assistant/intents/` | Flows conversacionales del asistente (YAML por `intent_id`) |
| `assistant/globals/` | Piezas reutilizables entre flows |
| `assistant/intent-classification-rules.yaml` | Vocabulario NL, score, fallbacks operativos |
| `assistant/assistant-shortcuts.yaml` | Atajos visibles del asistente |
| `assistant/hint-resolution.yaml` | Intents/prefixes de hints por entidad (scheduling, organization, person) |
| `permission/domain-operation-policies.yaml` | Operaciones RBAC → políticas de recurso |
| `ui/home_panel_manifest.yaml` | Layout del panel de inicio staff/paciente |
| `ui/client-context.yaml` | Flows/notificaciones paciente ocultos en web staff |
| `ui/json-domains.yaml` | Entidad API → carpeta `views/json/{dominio}/` |
| `ui/screen-params.yaml` | Expansión de params UI (p. ej. `slot_id` turnos) |
| `ui/select-option-sources.yaml` | Fuentes `option_config.source` → provider de dominio |
| `ai/clinical-text-ia.yaml` | Prompts SNOMED y heurísticas de confianza para corrección LLM |
| `terminology/snomed-categories.yaml` | ECL por categoría, mapeo extracción IA → SNOMED, umbral semántico |

Contrato de pasos YAML: `common/components/Platform/Assistant/SubIntentEngine/schemas/SUBINTENT_CONTRACT.md`.

Handlers de dominio (hydrators, políticas, scope, filtros, presentación, panel home, canal conversacional): `common/config/product-registries.php` vía `ProductRegistryConfig`.

Reglas NL del preprocess del chat y del clasificador: `assistant/intent-classification-rules.yaml` (`chat_preprocess`, `conversational_channel`, `match_rules`).
