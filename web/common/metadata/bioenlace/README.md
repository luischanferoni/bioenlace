# Metadata del producto Bioenlace

Metadata declarativa específica del rubro (salud). Los **motores genéricos** la consumen vía `common\components\Platform\Core\Product\ProductMetadataPaths`.

Para desplegar otro vertical: copiar esta carpeta, ajustar YAML y opcionalmente fijar `productMetadataDir` en `common/config/params-local.php`.

## Estructura

| Ruta | Contenido |
|------|-----------|
| `assistant/intents/` | Flows conversacionales del asistente (YAML por `intent_id`) |
| `assistant/globals/` | Piezas reutilizables entre flows |
| `assistant/prompts/` | Prompts por canal (`preprocess`, `conversational_clinical`, `informational_conversational`, `ambiguous_conversational`) |
| `assistant/routing/` | `intent-families`, `hint-resolution`, `booking-offer` |
| `assistant/copy/channel-copy.yaml` | Textos UX del asistente por perfil de cliente |
| `assistant/assistant-shortcuts.yaml` | Atajos visibles del asistente |
| `permission/domain-operation-policies.yaml` | Operaciones RBAC → políticas de recurso |
| `ui/home_panel_manifest.yaml` | Layout del panel de inicio staff/paciente |
| `ui/client-context.yaml` | Contextos por cliente (`mobile_paciente`, `whatsapp_paciente`) y ocultamiento staff |
| `ui/json-domains.yaml` | Entidad API → carpeta `views/json/{dominio}/` |
| `ui/screen-params.yaml` | Expansión de params UI (p. ej. `slot_id` turnos) |
| `ui/select-option-sources.yaml` | Fuentes `option_config.source` → provider de dominio |
| `ai/clinical-text-ia.yaml` | Prompts SNOMED, captura clínica, codificación automática y léxico clínico (`clinical_lexicon`) |
| `terminology/snomed-terminology.yaml` | ECL canónicos, codificación IA, perfiles Snowstorm |

Contrato de pasos YAML: `common/components/Platform/Assistant/SubIntentEngine/schemas/SUBINTENT_CONTRACT.md`.

Handlers de dominio (hydrators, políticas, scope, filtros, presentación, panel home, canal conversacional): `common/config/product-registries.php` vía `ProductRegistryConfig`.

Canal conversacional (copy): `assistant/prompts/conversational_clinical.yaml`. Booking: `assistant/routing/booking-offer.yaml`. Política síntoma/trámite: `ChatChannelPolicy` (PHP).
