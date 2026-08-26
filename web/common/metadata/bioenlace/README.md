# Metadata del producto Bioenlace

Metadata **declarativa del rubro** (salud). Los motores genéricos la consumen vía
`common\components\Platform\Core\Product\ProductMetadataPaths`.

**No es config de Yii.** `common/config/main.php` / `params.php` = runtime (DB, components, secretos).
Esta carpeta = composición del producto (flows, knobs, copy, catálogos, manifiestos).
Cableado `handler_id →` PHP: `common/config/product-registries.php`.

Para otro vertical: copiar la carpeta, ajustar YAML y opcionalmente
`productMetadataDir` en `params-local.php`.

Guía de límites YAML vs Yii: [`web/docs/arquitectura/metadata-yaml-uso.md`](../../../docs/arquitectura/metadata-yaml-uso.md).  
Maestros vs metadata (runtime + cognitivo): [`web/docs/arquitectura/runtime-datos-y-metadata.md`](../../../docs/arquitectura/runtime-datos-y-metadata.md).

## Tipología de archivos

| Tipo | Qué es | Qué no es |
|------|--------|-----------|
| **flow** | Guion conversacional (`when`/`next`, pantallas, draft) | Integridad clínica ni gates hard |
| **knob** | Umbrales, flags, overrides sobre policy PHP | Fuente de verdad de «¿puede emitirse?» |
| **copy** | Textos UX / prompts por canal o perfil | Predicados de dominio (van en PHP) |
| **routing** | Familias NL, hints, booking CTA, thread tags | `if intent_id` en orquestadores |
| **manifest** | Composición de superficie (panel, client-context, screen-params) | RBAC HTTP (eso es `permission/`) |
| **auth** | Capabilities, políticas de recurso, aliases legacy | Autorización ad hoc en controllers |

**No** usar metadata para maestros/catálogos de lookup en request (provincias, vecinos, recursos institucionales, etc.): van en **BD** + seed **console**. Ver ADR runtime datos vs metadata.

> Seeds one-shot / dumps Georef no viven aquí. Geo: tablas `geo_*` + console por país.
## Plantilla de cabecera (YAML nuevos o al tocar)

```yaml
# Tipo: flow | knob | copy | routing | manifest | auth
# Propósito: una línea
# Consumidor: ClassName / ProductMetadataPaths::foo()
# No poner aquí: integridad clínica / gates hard / maestros de lookup (van en BD)
```

## Estructura

| Ruta | Tipo | Contenido |
|------|------|-----------|
| `assistant/intents/` | flow | Flows por `intent_id` (`create`/`read`/`update`/`delete`) |
| `assistant/globals/` | flow | Piezas reutilizables entre flows |
| `assistant/prompts/` | copy | Prompts por canal (`preprocess`, `conversational_clinical`, …) |
| `assistant/routing/` | routing | `intent-families`, `hint-resolution`, `booking-offer`, `thread-state` |
| `assistant/copy/channel-copy.yaml` | copy | Textos UX por perfil de cliente (`X-App-Client`) |
| `assistant/assistant-shortcuts.yaml` | manifest | Atajos visibles (si el catálogo está desplegado) |
| `assistant/assistant-shortcut-group-labels.yaml` | manifest | Etiquetas/orden de grupos de atajos |
| `agents/` | knob | Política operativa por `agent_id` (umbrales; gates hard en dominio) |
| `permission/` | auth | `domain-operation-policies`, `legacy-permission-aliases`, `capabilities/` |
| `permission/migration/` | auth | Mapas one-shot de grants (`intent-grant-migration-map`) |
| `ui/home-panel-manifest.yaml` | manifest | Layout panel inicio staff/paciente |
| `ui/client-context.yaml` | manifest | Contextos por cliente y ocultamiento staff |
| `ui/json-domains.yaml` | catalog | Entidad API → carpeta `views/json/{dominio}/` |
| `ui/screen-params.yaml` | manifest | Expansión de params UI |
| `ui/select-option-sources.yaml` | catalog | `option_config.source` → provider de dominio |
| `ui/paciente-contexto-offering.yaml` | manifest | Ofertas de contexto paciente |
| `ai/clinical-text-ia.yaml` | copy + knob | Prompts SNOMED/captura + overrides de post-proceso |
| `ai/ai-cost-reference.yaml` | catalog | Tarifas/referencia de costo IA |
| `terminology/` | catalog | SNOMED ECL, sinónimos de servicio institucional |
| `clinical/pedido-atencion.yaml` | knob + catalog | Systems, modos, capacity_rules, aliases NL de acto |
| `organization/` | knob | Agenda por encounter class, pricing PES, atributos efector |
| `scheduling/turno-behavior-profile.yaml` | catalog | Eventos/métricas de comportamiento (no risk policy) |
| `person/ventanilla-sesion.yaml` | knob | Ventanilla / sesión persona |
| `integrations/` | *(revisar)* | Si es lookup runtime → BD; si es mapa de motor → OK |

> Geo multi-país: tablas `geo_paises`, `geo_provincias`, `geo_provincia_vecinos`, `geo_recursos_*`. Seeds: `php yii clinical-seed/geo-multipais`.

Contrato de pasos de intent: `common/components/Platform/Assistant/SubIntentEngine/schemas/SUBINTENT_CONTRACT.md`.

Canal síntoma/trámite/menú: `ChatChannelPolicy` (PHP). Copy clínico: `assistant/prompts/conversational_clinical.yaml`.
Booking CTA: `assistant/routing/booking-offer.yaml`.
