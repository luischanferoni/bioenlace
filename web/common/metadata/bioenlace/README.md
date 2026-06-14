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
| `permission/domain-operation-policies.yaml` | Operaciones RBAC → políticas de recurso |
| `ui/home_panel_manifest.yaml` | Layout del panel de inicio staff/paciente |

Contrato de pasos YAML: `common/components/Platform/Assistant/SubIntentEngine/schemas/SUBINTENT_CONTRACT.md`.

Handlers de dominio (hydrators, políticas, scope, filtros, presentación, panel home, canal conversacional): `common/config/product-registries.php` vía `ProductRegistryConfig`.

Reglas NL del preprocess del chat y del clasificador: `assistant/intent-classification-rules.yaml` (`chat_preprocess`, `conversational_channel`, `match_rules`).
